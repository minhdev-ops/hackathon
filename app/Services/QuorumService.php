<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\ChangeRequest;
use App\Models\ChangeRequestApproval;
use App\Models\User;
use App\Support\ChangeRequestType;
use Illuminate\Support\Facades\DB;

/**
 * Change Request lifecycle: create, approve, reject, execute (FR-QRM-02/03).
 *
 * Every state transition locks the CR row with SELECT ... FOR UPDATE, so two
 * admins approving at the same time can never execute a payload twice.
 */
class QuorumService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly ChangeRequestExecutor $executor,
        private readonly QuorumSettings $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function request(User $admin, string $type, array $payload): ChangeRequest
    {
        $validated = ChangeRequestType::validate($type, $payload);
        $requiredApprovals = $this->settings->threshold();

        $changeRequest = DB::transaction(function () use ($admin, $type, $validated, $requiredApprovals) {
            $changeRequest = ChangeRequest::create([
                'type' => $type,
                'payload' => $validated,
                'requested_by' => $admin->id,
                'status' => ChangeRequest::STATUS_PENDING,
                'required_approvals' => $requiredApprovals,
            ]);

            $this->audit->record(
                'change_request.created',
                AuditService::ACTOR_USER,
                $admin->id,
                'change_request',
                $changeRequest->getKey(),
                [
                    'type' => $type,
                    'required_approvals' => $requiredApprovals,
                ],
            );

            return $changeRequest;
        });

        return $changeRequest->load(['requester', 'approvals.admin']);
    }

    public function approve(ChangeRequest $changeRequest, User $admin, ?string $signature = null): ChangeRequest
    {
        return DB::transaction(function () use ($changeRequest, $admin, $signature) {
            $locked = $this->lock($changeRequest);

            $this->assertPending($locked);
            $this->assertHasNotVoted($locked, $admin);

            ChangeRequestApproval::create([
                'change_request_id' => $locked->getKey(),
                'admin_id' => $admin->id,
                'decision' => 'approve',
                'signature' => $signature,
            ]);

            $approvals = $locked->approvals()->where('decision', 'approve')->count();

            $this->audit->record(
                'change_request.approved',
                AuditService::ACTOR_USER,
                $admin->id,
                'change_request',
                $locked->getKey(),
                [
                    'approvals_count' => $approvals,
                    'required_approvals' => $locked->required_approvals,
                ],
            );

            if ($approvals >= $locked->required_approvals) {
                $this->executor->execute($locked);

                $locked->forceFill([
                    'status' => ChangeRequest::STATUS_EXECUTED,
                    'executed_at' => now(),
                ])->save();

                $this->audit->record(
                    'change_request.executed',
                    AuditService::ACTOR_SYSTEM,
                    null,
                    'change_request',
                    $locked->getKey(),
                    [
                        'type' => $locked->type,
                        'approvals_count' => $approvals,
                    ],
                );
            }

            return $locked->refresh()->load(['requester', 'approvals.admin']);
        });
    }

    public function reject(ChangeRequest $changeRequest, User $admin): ChangeRequest
    {
        return DB::transaction(function () use ($changeRequest, $admin) {
            $locked = $this->lock($changeRequest);

            $this->assertPending($locked);
            $this->assertHasNotVoted($locked, $admin);

            ChangeRequestApproval::create([
                'change_request_id' => $locked->getKey(),
                'admin_id' => $admin->id,
                'decision' => 'reject',
            ]);

            $rejections = $locked->approvals()->where('decision', 'reject')->count();

            $this->audit->record(
                'change_request.vote_rejected',
                AuditService::ACTOR_USER,
                $admin->id,
                'change_request',
                $locked->getKey(),
                [
                    'rejections_count' => $rejections,
                    'required_approvals' => $locked->required_approvals,
                ],
            );

            if ($rejections >= $locked->required_approvals) {
                $locked->forceFill(['status' => ChangeRequest::STATUS_REJECTED])->save();

                $this->audit->record(
                    'change_request.rejected',
                    AuditService::ACTOR_SYSTEM,
                    null,
                    'change_request',
                    $locked->getKey(),
                    [
                        'type' => $locked->type,
                        'rejections_count' => $rejections,
                    ],
                );
            }

            return $locked->refresh()->load(['requester', 'approvals.admin']);
        });
    }

    private function lock(ChangeRequest $changeRequest): ChangeRequest
    {
        return ChangeRequest::query()
            ->whereKey($changeRequest->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function assertPending(ChangeRequest $changeRequest): void
    {
        if (! $changeRequest->isPending()) {
            throw new ApiException(
                'CHANGE_REQUEST_CLOSED',
                'Change Request đã được xử lý, không thể bỏ phiếu thêm.',
                409,
            );
        }
    }

    private function assertHasNotVoted(ChangeRequest $changeRequest, User $admin): void
    {
        if ($changeRequest->approvals()->where('admin_id', $admin->id)->exists()) {
            throw new ApiException(
                'ALREADY_VOTED',
                'Bạn đã bỏ phiếu cho Change Request này.',
                409,
            );
        }
    }
}
