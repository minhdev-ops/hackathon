<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Agent;
use App\Models\AgentTask;
use App\Models\Playbook;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TaskService
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    public function create(User $owner, string $playbookSlug, string $instruction): array
    {
        $playbook = Playbook::where('slug', $playbookSlug)->where('active', true)->first();

        if (! $playbook) {
            throw new ApiException(
                'PLAYBOOK_NOT_FOUND',
                'Không tìm thấy playbook hoặc playbook đang tắt.',
                422,
            );
        }

        $agent = Agent::query()
            ->where('status', Agent::STATUS_ACTIVE)
            ->get()
            ->first(fn (Agent $candidate) => $candidate->allowsPlaybook($playbook->slug));

        if (! $agent) {
            throw new ApiException(
                'AGENT_UNAVAILABLE',
                'Không có agent đang hoạt động cho playbook này.',
                422,
            );
        }

        return DB::transaction(function () use ($owner, $playbook, $agent, $instruction) {
            $task = AgentTask::create([
                'owner_id' => $owner->getKey(),
                'agent_id' => $agent->getKey(),
                'playbook_id' => $playbook->getKey(),
                'instruction' => $instruction,
                'status' => AgentTask::STATUS_PROPOSING,
                'proposal' => null,
                'result' => null,
                'log' => [],
            ]);

            $task->appendLog('task_created', [
                'playbook_slug' => $playbook->slug,
                'agent_id' => $agent->getKey(),
            ]);

            $this->audit->record(
                'task.created',
                AuditService::ACTOR_USER,
                $owner->getKey(),
                'agent_task',
                $task->getKey(),
                [
                    'playbook_slug' => $playbook->slug,
                    'agent_id' => $agent->getKey(),
                ],
            );

            return [
                'task' => $task,
                'playbook' => $playbook,
            ];
        });
    }
}
