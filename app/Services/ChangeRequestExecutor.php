<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Agent;
use App\Models\ChangeRequest;
use App\Models\Playbook;
use App\Models\User;
use Illuminate\Support\Arr;

/**
 * Applies an approved CR payload. Called inside the approval transaction so a
 * payload can never be half-applied (FR-QRM-03).
 */
class ChangeRequestExecutor
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly QuorumSettings $settings,
    ) {}

    public function execute(ChangeRequest $changeRequest): void
    {
        match ($changeRequest->type) {
            ChangeRequest::TYPE_ROLE_GRANT => $this->grantRole($changeRequest),
            ChangeRequest::TYPE_ROLE_REVOKE => $this->revokeRole($changeRequest),
            ChangeRequest::TYPE_PLAYBOOK_UPDATE => $this->updatePlaybook($changeRequest),
            ChangeRequest::TYPE_AGENT_ALLOWLIST_UPDATE => $this->updateAgentAllowlist($changeRequest),
            ChangeRequest::TYPE_QUORUM_THRESHOLD_UPDATE => $this->updateQuorumThreshold($changeRequest),
            default => throw new ApiException(
                'INVALID_CHANGE_REQUEST_TYPE',
                'Loại Change Request không hợp lệ.',
                422,
            ),
        };
    }

    private function grantRole(ChangeRequest $changeRequest): void
    {
        $payload = $changeRequest->payload;
        $user = $this->findUser($payload);
        $role = (string) ($payload['role'] ?? '');

        $user->forceFill(['role' => $role])->save();

        $this->audit->record(
            'role.granted',
            AuditService::ACTOR_SYSTEM,
            null,
            'user',
            $user->id,
            ['role' => $role, 'change_request_id' => $changeRequest->getKey()],
        );
    }

    private function revokeRole(ChangeRequest $changeRequest): void
    {
        $payload = $changeRequest->payload;
        $user = $this->findUser($payload);
        $role = (string) ($payload['role'] ?? '');

        // owner is the base role in this prototype: revoking admin/agent falls back to it.
        $user->forceFill(['role' => 'owner'])->save();

        $this->audit->record(
            'role.revoked',
            AuditService::ACTOR_SYSTEM,
            null,
            'user',
            $user->id,
            ['role' => $role, 'new_role' => 'owner', 'change_request_id' => $changeRequest->getKey()],
        );
    }

    private function updatePlaybook(ChangeRequest $changeRequest): void
    {
        $payload = $changeRequest->payload;
        $playbook = Playbook::query()->where('slug', $payload['slug'] ?? '')->first();

        if ($playbook === null) {
            throw new ApiException(
                'CHANGE_REQUEST_EXECUTION_FAILED',
                'Playbook không còn tồn tại.',
                422,
            );
        }

        $mutable = [
            'required_doc_types',
            'optional_doc_types',
            'forbidden_doc_types',
            'recipient_label',
            'default_ttl_minutes',
            'max_ttl_minutes',
        ];

        $merged = array_merge($playbook->only($mutable), Arr::only($payload, $mutable));

        if ((int) $merged['default_ttl_minutes'] > (int) $merged['max_ttl_minutes']) {
            throw new ApiException(
                'CHANGE_REQUEST_EXECUTION_FAILED',
                'default_ttl_minutes không được lớn hơn max_ttl_minutes.',
                422,
            );
        }

        $playbook->forceFill($merged)->forceFill(['version' => $playbook->version + 1])->save();

        $this->audit->record(
            'playbook.updated',
            AuditService::ACTOR_SYSTEM,
            null,
            'playbook',
            $playbook->id,
            [
                'slug' => $playbook->slug,
                'version' => $playbook->version,
                'changes' => Arr::only($payload, $mutable),
                'change_request_id' => $changeRequest->getKey(),
            ],
        );
    }

    private function updateAgentAllowlist(ChangeRequest $changeRequest): void
    {
        $payload = $changeRequest->payload;
        $agent = Agent::query()->where('client_id', $payload['client_id'] ?? '')->first();

        if ($agent === null) {
            throw new ApiException(
                'CHANGE_REQUEST_EXECUTION_FAILED',
                'Agent không còn tồn tại.',
                422,
            );
        }

        $allowedPlaybooks = array_values($payload['allowed_playbooks'] ?? []);
        $agent->forceFill(['allowed_playbooks' => $allowedPlaybooks])->save();

        $this->audit->record(
            'agent.allowlist_updated',
            AuditService::ACTOR_SYSTEM,
            null,
            'agent',
            $agent->id,
            [
                'client_id' => $agent->client_id,
                'allowed_playbooks' => $allowedPlaybooks,
                'change_request_id' => $changeRequest->getKey(),
            ],
        );
    }

    private function updateQuorumThreshold(ChangeRequest $changeRequest): void
    {
        $threshold = (int) ($changeRequest->payload['required_approvals'] ?? 0);

        $this->settings->setThreshold($threshold);

        $this->audit->record(
            'quorum.threshold_updated',
            AuditService::ACTOR_SYSTEM,
            null,
            'setting',
            QuorumSettings::KEY_THRESHOLD,
            [
                'required_approvals' => $threshold,
                'change_request_id' => $changeRequest->getKey(),
            ],
        );
    }

    /**
     * Accepts the canonical {email} payload and the legacy {user_id} shape
     * used by the demo controller, so an approved CR always resolves.
     *
     * @param  array<string, mixed>  $payload
     */
    private function findUser(array $payload): User
    {
        $email = $payload['email'] ?? null;
        $userId = $payload['user_id'] ?? null;

        $user = is_string($email)
            ? User::query()->where('email', $email)->first()
            : null;

        if ($user === null && is_numeric($userId)) {
            $user = User::query()->find((int) $userId);
        }

        if ($user === null) {
            throw new ApiException(
                'CHANGE_REQUEST_EXECUTION_FAILED',
                'Người dùng không còn tồn tại.',
                422,
            );
        }

        return $user;
    }
}
