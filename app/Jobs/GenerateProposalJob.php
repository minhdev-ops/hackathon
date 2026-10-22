<?php

namespace App\Jobs;

use App\Llm\LlmClient;
use App\Models\AgentTask;
use App\Models\Grant;
use App\Models\Playbook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * FR-AGENT-01: Tạo đề xuất JSON từ playbook qua LlmClient.
 * Đề xuất bị cắt về phạm vi playbook trước khi lưu (dù LLM trả loại ngoài).
 * Sau đó tạo grant pending để Owner duyệt (FR-GRANT-01/02).
 */
class GenerateProposalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        public readonly string $taskId,
    ) {}

    public function handle(LlmClient $llm): void
    {
        $task = AgentTask::with(['playbook', 'agent'])->find($this->taskId);

        if (! $task || $task->status !== AgentTask::STATUS_PROPOSING) {
            return;
        }

        $playbook = $task->playbook;

        if (! $playbook instanceof Playbook) {
            $task->forceFill(['status' => AgentTask::STATUS_FAILED_REVOKED])->save();
            $task->appendLog('proposal.failed', ['reason' => 'playbook_not_found']);

            return;
        }

        $task->appendLog('proposal.started', ['playbook_slug' => $playbook->slug]);

        $raw = $llm->propose($playbook, $task->instruction);

        // Defense in depth: cắt đề xuất về phạm vi playbook, bất kể LLM trả gì.
        $allowed = array_values(array_unique([
            ...($playbook->required_doc_types ?? []),
            ...($playbook->optional_doc_types ?? []),
        ]));

        $required = $this->inScope($raw['required'] ?? [], $allowed);
        $optional = array_values(array_diff($this->inScope($raw['optional'] ?? [], $allowed), $required));

        $rationale = is_string($raw['rationale'] ?? null) && trim($raw['rationale']) !== ''
            ? Str::limit($raw['rationale'], 1000)
            : 'Đề xuất dựa trên playbook '.$playbook->name.'.';

        $proposal = [
            'required' => $required,
            'optional' => $optional,
            'rationale' => $rationale,
        ];

        $task->forceFill([
            'proposal' => $proposal,
            'status' => AgentTask::STATUS_PROPOSAL_READY,
        ])->save();

        $task->appendLog('proposal.generated', [
            'required' => $required,
            'optional' => $optional,
        ]);

        $grant = Grant::firstOrCreate(
            [
                'task_id' => $task->getKey(),
                'status' => Grant::STATUS_PENDING,
            ],
            [
                'owner_id' => $task->owner_id,
                'agent_id' => $task->agent_id,
                'purpose' => Str::limit($task->instruction, 240),
                'doc_ids' => [],
            ],
        );

        $task->forceFill(['status' => AgentTask::STATUS_AWAITING_APPROVAL])->save();
        $task->appendLog('awaiting_approval', [
            'grant_id' => $grant->getKey(),
            'required_approvals' => count($required),
        ]);
    }

    /**
     * Chỉ giữ doc_type thuộc required + optional của playbook.
     *
     * @return list<string>
     */
    private function inScope(mixed $types, array $allowed): array
    {
        if (! is_array($types)) {
            return [];
        }

        $inScope = [];

        foreach ($types as $type) {
            if (is_string($type) && in_array($type, $allowed, true)) {
                $inScope[] = $type;
            }
        }

        return array_values(array_unique($inScope));
    }
}
