<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Exceptions\GrantUnavailableException;
use App\Jobs\RunAgentJob;
use App\Models\Agent;
use App\Models\AgentTask;
use App\Models\Document;
use App\Models\Grant;
use App\Models\Playbook;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Grant lifecycle: approve (FR-GRANT-02), deny (FR-GRANT-06),
 * revoke (FR-GRANT-03), expire (FR-GRANT-04) and agent open.
 */
class GrantService
{
    public function __construct(
        private readonly GrantKeyStore $keys,
        private readonly GrantBundleCipher $cipher,
        private readonly AuditService $audit,
    ) {}

    /**
     * Owner approves the proposal: scope + TTL are checked, the bundle is
     * stored, K_g goes to Redis with the grant TTL and the grant turns active.
     */
    public function approve(
        Grant $grant,
        array $docIds,
        int $ttlMinutes,
        string $bundleIv,
        string $grantKeyB64,
        UploadedFile $bundle,
    ): Grant {
        if (! $grant->isPending()) {
            throw new ApiException('GRANT_NOT_PENDING', 'Grant không ở trạng thái chờ duyệt.', 409);
        }

        $grant->loadMissing('task.playbook');
        $playbook = $grant->task?->playbook;

        if (! $playbook instanceof Playbook) {
            throw new ApiException('PLAYBOOK_UNAVAILABLE', 'Không tìm thấy playbook của task.', 422);
        }

        $allowedTtls = $playbook->allowedTtlOptions(config('trustgate.grant_ttl_options'));

        if (! in_array($ttlMinutes, $allowedTtls, true)) {
            throw new ApiException(
                'TTL_NOT_ALLOWED',
                'TTL không hợp lệ hoặc vượt TTL tối đa của playbook.',
                422,
                [
                    'allowed_ttl_minutes' => $allowedTtls,
                    'max_ttl_minutes' => $playbook->max_ttl_minutes,
                ],
            );
        }

        $docIds = array_values(array_unique($docIds));
        $this->assertDocumentsInScope($grant, $docIds, $playbook);
        $this->assertRequiredDocumentsPresent($grant, $docIds, $playbook);
        $this->assertKeyMaterial($grantKeyB64, $bundleIv);

        $ciphertext = $bundle->get();

        if ($ciphertext === '' || strlen($ciphertext) <= (int) config('trustgate.grant_bundle_tag_bytes')) {
            throw new ApiException('INVALID_BUNDLE', 'Bundle rỗng hoặc không hợp lệ.', 422);
        }

        $payload = $this->cipher->decrypt($ciphertext, $bundleIv, $grantKeyB64);
        $this->assertBundleScope($payload, $docIds);

        $disk = config('trustgate.grant_bundle_disk');
        $storedPath = $bundle->storeAs(
            config('trustgate.grant_bundle_path_prefix').'/'.$grant->getKey(),
            'bundle.bin',
            ['disk' => $disk],
        );

        if (! is_string($storedPath)) {
            throw new ApiException('BUNDLE_STORE_FAILED', 'Không lưu được bundle.', 500);
        }

        $expiresAt = now()->addMinutes($ttlMinutes);
        $ttlSeconds = max(1, $expiresAt->getTimestamp() - now()->getTimestamp());
        $taskToRun = null;

        try {
            DB::transaction(function () use ($grant, $docIds, $ttlMinutes, $bundleIv, $grantKeyB64, $storedPath, $expiresAt, $ttlSeconds, &$taskToRun) {
                $grant->forceFill([
                    'doc_ids' => $docIds,
                    'status' => Grant::STATUS_ACTIVE,
                    'approved_at' => now(),
                    'expires_at' => $expiresAt,
                    'revoked_at' => null,
                    'bundle_path' => $storedPath,
                    'bundle_iv' => $bundleIv,
                ])->save();

                $this->keys->put($grant, $grantKeyB64, $ttlSeconds);

                $task = $grant->task;
                if ($task !== null) {
                    $task->forceFill(['status' => AgentTask::STATUS_RUNNING])->save();
                    $task->appendLog('grant_approved', [
                        'grant_id' => $grant->getKey(),
                        'ttl_minutes' => $ttlMinutes,
                    ]);

                    // FR-AGENT-02: agent bắt đầu chạy khi grant active (sau commit).
                    $taskToRun = $task;
                }

                $this->audit->record(
                    'grant.approved',
                    AuditService::ACTOR_USER,
                    $grant->owner_id,
                    'grant',
                    $grant->getKey(),
                    [
                        'doc_ids' => $docIds,
                        'ttl_minutes' => $ttlMinutes,
                        'expires_at' => $expiresAt->toIso8601String(),
                    ],
                );
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($storedPath);

            if ($exception instanceof ApiException) {
                throw $exception;
            }

            throw new ApiException(
                'GRANT_KEY_STORE_UNAVAILABLE',
                'Không lưu được K_g cho grant, vui lòng thử lại.',
                503,
            );
        }

        if ($taskToRun !== null) {
            try {
                RunAgentJob::dispatch($taskToRun->getKey());
            } catch (Throwable $exception) {
                // Grant đã active; job có thể được đẩy lại mà không rollback grant.
                report($exception);
            }
        }

        return $grant->refresh();
    }

    public function deny(Grant $grant, ?string $reason = null): Grant
    {
        if (! $grant->isPending()) {
            throw new ApiException('GRANT_NOT_PENDING', 'Grant không ở trạng thái chờ duyệt.', 409);
        }

        $grant->loadMissing('task');

        DB::transaction(function () use ($grant, $reason) {
            $grant->forceFill(['status' => Grant::STATUS_DENIED])->save();

            $task = $grant->task;
            if ($task !== null) {
                $task->forceFill(['status' => AgentTask::STATUS_DENIED])->save();
                $task->appendLog('grant_denied', [
                    'grant_id' => $grant->getKey(),
                    'reason' => $reason,
                ]);
            }

            $this->audit->record(
                'grant.denied',
                AuditService::ACTOR_USER,
                $grant->owner_id,
                'grant',
                $grant->getKey(),
                ['reason' => $reason],
            );
        });

        return $grant->refresh();
    }

    /**
     * Revoke deletes K_g (crypto-shredding) and fails any running task at once.
     */
    public function revoke(Grant $grant, ?string $reason = null): Grant
    {
        if (! $grant->isActive()) {
            if ($grant->status === Grant::STATUS_ACTIVE) {
                $this->expireOne($grant);
            }

            throw new ApiException('GRANT_NOT_ACTIVE', 'Chỉ thu hồi được grant đang hiệu lực.', 409);
        }

        $grant->loadMissing('task');

        DB::transaction(function () use ($grant, $reason) {
            $this->keys->forget($grant);

            $grant->forceFill([
                'status' => Grant::STATUS_REVOKED,
                'revoked_at' => now(),
            ])->save();

            $task = $grant->task;
            if ($task !== null && $task->status === AgentTask::STATUS_RUNNING) {
                $task->forceFill(['status' => AgentTask::STATUS_FAILED_REVOKED])->save();
                $task->appendLog('grant_revoked', [
                    'grant_id' => $grant->getKey(),
                    'reason' => $reason ?? 'owner_revoked',
                ]);
            }

            $this->audit->record(
                'grant.revoked',
                AuditService::ACTOR_USER,
                $grant->owner_id,
                'grant',
                $grant->getKey(),
                ['reason' => $reason],
            );
        });

        return $grant->refresh();
    }

    /**
     * Scheduled every minute (FR-GRANT-04): flip over-due active grants to expired.
     */
    public function expireStale(int $chunk = 100): int
    {
        $expired = 0;

        Grant::query()
            ->where('status', Grant::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->chunkById($chunk, function ($grants) use (&$expired) {
                foreach ($grants as $grant) {
                    if ($this->expireOne($grant)) {
                        $expired++;
                    }
                }
            });

        return $expired;
    }

    public function expireOne(Grant $grant): bool
    {
        if (
            $grant->status !== Grant::STATUS_ACTIVE
            || $grant->expires_at === null
            || $grant->expires_at->isFuture()
        ) {
            return false;
        }

        DB::transaction(function () use ($grant) {
            $this->keys->forget($grant);

            $grant->forceFill(['status' => Grant::STATUS_EXPIRED])->save();

            $this->audit->record(
                'grant.expired',
                AuditService::ACTOR_SYSTEM,
                null,
                'grant',
                $grant->getKey(),
                ['expires_at' => $grant->expires_at?->toIso8601String()],
            );
        });

        $grant->refresh();

        return true;
    }

    /**
     * Agent opens an active grant: read K_g from Redis, decrypt the bundle in
     * memory and return the approved documents. Every attempt is audited.
     *
     * @return array<string, mixed>
     */
    public function open(Grant $grant): array
    {
        if ($grant->status === Grant::STATUS_ACTIVE && ! $grant->isActive()) {
            $this->expireOne($grant);
            $grant->refresh();
        }

        if (! $grant->isActive()) {
            $this->auditOpenDenied($grant);
            throw GrantUnavailableException::make();
        }

        $grant->loadMissing('agent');

        if ($grant->agent?->status !== Agent::STATUS_ACTIVE) {
            $this->auditOpenDenied($grant, 'agent_disabled');
            throw new ApiException('AGENT_DISABLED', 'Agent đã bị vô hiệu hoá.', 403);
        }

        $key = $this->keys->get($grant);

        $ciphertext = $key !== null && $grant->bundle_path !== null
            ? Storage::disk(config('trustgate.grant_bundle_disk'))->get($grant->bundle_path)
            : null;

        if ($key === null || ! is_string($ciphertext) || $ciphertext === '') {
            $this->auditOpenDenied($grant, 'key_unavailable');
            throw GrantUnavailableException::make();
        }

        $payload = $this->cipher->decrypt($ciphertext, (string) $grant->bundle_iv, $key);
        $documents = $this->assertBundleScope($payload, $grant->docIds());

        $this->audit->record(
            'grant.opened',
            AuditService::ACTOR_AGENT,
            $grant->agent_id,
            'grant',
            $grant->getKey(),
            [
                'doc_ids' => $grant->docIds(),
                'doc_count' => count($documents),
                'expires_at' => $grant->expires_at?->toIso8601String(),
            ],
        );

        return [
            'grant_id' => $grant->getKey(),
            'documents' => $documents,
            'opened_at' => now()->toIso8601String(),
            'expires_at' => $grant->expires_at?->toIso8601String(),
            'remaining_seconds' => $grant->remainingSeconds(),
        ];
    }

    /**
     * @param  list<string>  $docIds
     */
    private function assertDocumentsInScope(Grant $grant, array $docIds, Playbook $playbook): void
    {
        if ($docIds === []) {
            throw ApiException::outOfScope('Cần chọn ít nhất một tài liệu.');
        }

        $documents = Document::query()->whereIn('id', $docIds)->get();

        $violations = [];

        foreach ($docIds as $docId) {
            $document = $documents->firstWhere('id', $docId);

            if ($document === null || $document->owner_id !== $grant->owner_id) {
                $violations[] = ['doc_id' => $docId, 'reason' => 'NOT_AVAILABLE'];

                continue;
            }

            if (! $playbook->allowsDocType($document->doc_type)) {
                $violations[] = [
                    'doc_id' => $docId,
                    'doc_type' => $document->doc_type,
                    'reason' => 'OUT_OF_SCOPE',
                ];
            }
        }

        if ($violations !== []) {
            throw ApiException::outOfScope(
                'Tài liệu nằm ngoài phạm vi playbook hoặc không thuộc Owner.',
                ['violations' => $violations],
            );
        }
    }

    /**
     * FR-GRANT-02: every required doc type must have at least one selected,
     * owner-owned document. The playbook is the floor, even when the proposal
     * asks for fewer documents.
     *
     * @param  list<string>  $docIds
     */
    private function assertRequiredDocumentsPresent(Grant $grant, array $docIds, Playbook $playbook): void
    {
        $proposal = $grant->task?->proposal;
        $proposalRequired = is_array($proposal) && is_array($proposal['required'] ?? null)
            ? array_values(array_filter($proposal['required'], 'is_string'))
            : [];

        $required = array_values(array_unique([
            ...($playbook->required_doc_types ?? []),
            ...$proposalRequired,
        ]));

        if ($required === []) {
            return;
        }

        $selectedTypes = Document::query()
            ->whereIn('id', $docIds)
            ->where('owner_id', $grant->owner_id)
            ->pluck('doc_type')
            ->all();

        $missing = array_values(array_diff($required, $selectedTypes));

        if ($missing !== []) {
            throw new ApiException(
                'MISSING_REQUIRED_DOCS',
                'Còn thiếu tài liệu bắt buộc trong hồ sơ.',
                422,
                ['missing_doc_types' => $missing],
            );
        }
    }

    private function assertKeyMaterial(string $grantKeyB64, string $bundleIv): void
    {
        $key = base64_decode($grantKeyB64, true);
        $iv = base64_decode($bundleIv, true);

        if ($key === false || strlen($key) !== (int) config('trustgate.grant_bundle_key_bytes')) {
            throw new ApiException('INVALID_GRANT_KEY', 'grant_key_b64 phải là 32 byte mã hoá base64.', 422);
        }

        if ($iv === false || strlen($iv) !== (int) config('trustgate.grant_bundle_iv_bytes')) {
            throw new ApiException('INVALID_BUNDLE_IV', 'bundle_iv phải là 12 byte mã hoá base64.', 422);
        }
    }

    /**
     * The bundle must contain exactly the documents the Owner approved, nothing else.
     *
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $allowedDocIds
     * @return list<array<string, mixed>>
     */
    private function assertBundleScope(array $payload, array $allowedDocIds): array
    {
        $documents = $payload['documents'] ?? [];
        $bundleDocIds = [];

        foreach ($documents as $document) {
            $docId = is_array($document) ? ($document['doc_id'] ?? null) : null;

            if (! is_string($docId)) {
                throw new ApiException('INVALID_BUNDLE', 'Bundle thiếu doc_id hợp lệ.', 422);
            }

            $bundleDocIds[] = $docId;
        }

        $duplicates = count($bundleDocIds) !== count(array_unique($bundleDocIds));
        $missing = array_diff($allowedDocIds, $bundleDocIds) !== [];
        $extra = array_diff($bundleDocIds, $allowedDocIds) !== [];

        if ($documents === [] || $duplicates || $missing || $extra) {
            throw new ApiException(
                'BUNDLE_INVALID',
                'Bundle không khớp danh sách tài liệu đã duyệt.',
                422,
            );
        }

        return array_values($documents);
    }

    private function auditOpenDenied(Grant $grant, string $reason = 'not_active'): void
    {
        $this->audit->record(
            'grant.open_denied',
            AuditService::ACTOR_AGENT,
            $grant->agent_id,
            'grant',
            $grant->getKey(),
            [
                'reason' => $reason,
                'status' => $grant->status,
                'expires_at' => $grant->expires_at?->toIso8601String(),
            ],
        );
    }
}
