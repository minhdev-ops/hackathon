<?php

namespace App\Http\Resources;

use App\Models\ChangeRequest;
use App\Support\ChangeRequestType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChangeRequest
 */
class ChangeRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ChangeRequest $changeRequest */
        $changeRequest = $this->resource;

        $approvals = $changeRequest->relationLoaded('approvals')
            ? $changeRequest->approvals
            : $changeRequest->approvals()->with('admin')->get();

        $approved = $approvals->where('decision', 'approve');
        $rejected = $approvals->where('decision', 'reject');
        $myVote = $request->user() === null
            ? null
            : $approvals->firstWhere('admin_id', $request->user()->id);

        return [
            'id' => $changeRequest->id,
            'type' => $changeRequest->type,
            'type_label' => ChangeRequestType::label($changeRequest->type),
            'status' => $changeRequest->status,
            'payload' => $changeRequest->payload,
            'required_approvals' => (int) $changeRequest->required_approvals,
            'approvals_count' => $approved->count(),
            'rejections_count' => $rejected->count(),
            'remaining_approvals' => max(0, (int) $changeRequest->required_approvals - $approved->count()),
            'requester' => [
                'id' => $changeRequest->requested_by,
                'name' => $changeRequest->requester?->name,
                'email' => $changeRequest->requester?->email,
            ],
            'approvals' => $approvals
                ->sortBy('created_at')
                ->map(fn ($approval) => [
                    'admin_id' => $approval->admin_id,
                    'admin_name' => $approval->admin?->name,
                    'decision' => $approval->decision,
                    'has_signature' => $approval->signature !== null,
                    'at' => $approval->created_at?->toIso8601String(),
                ])
                ->values(),
            'my_decision' => $myVote?->decision,
            'can_vote' => $changeRequest->status === ChangeRequest::STATUS_PENDING && $myVote === null,
            'created_at' => $changeRequest->created_at?->toIso8601String(),
            'executed_at' => $changeRequest->executed_at?->toIso8601String(),
        ];
    }
}
