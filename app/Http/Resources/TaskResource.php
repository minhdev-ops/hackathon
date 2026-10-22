<?php

namespace App\Http\Resources;

use App\Models\AgentTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Chi tiết task cho màn hình /tasks/:id (FR-TASK-01, FR-AGENT-01).
 *
 * @mixin AgentTask
 */
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var AgentTask $task */
        $task = $this->resource;
        $task->loadMissing(['agent', 'playbook', 'grants']);

        $grant = $task->grants->sortByDesc('created_at')->first();

        return [
            'id' => $task->getKey(),
            'status' => $task->status,
            'instruction' => $task->instruction,
            'proposal' => $task->proposal,
            'result' => $task->result,
            'log' => $task->log ?? [],
            'agent' => [
                'id' => $task->agent_id,
                'name' => $task->agent?->name,
            ],
            'playbook' => $task->playbook ? [
                'id' => $task->playbook->getKey(),
                'slug' => $task->playbook->slug,
                'name' => $task->playbook->name,
            ] : null,
            'grant' => $grant === null ? null : [
                'id' => $grant->getKey(),
                'status' => $grant->status,
                'approved_at' => $grant->approved_at?->toIso8601String(),
                'expires_at' => $grant->expires_at?->toIso8601String(),
                'remaining_seconds' => $grant->remainingSeconds(),
            ],
            'owner_id' => $task->owner_id,
            'created_at' => $task->created_at?->toIso8601String(),
            'updated_at' => $task->updated_at?->toIso8601String(),
        ];
    }
}
