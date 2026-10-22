<?php

namespace App\Jobs;

use App\Llm\LlmClient;
use App\Llm\MockLlmClient;
use App\Models\AgentTask;
use App\Models\Grant;
use App\Services\GrantKeyStore;
use App\Services\GrantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

/**
 * FR-AGENT-02 + FR-AGENT-03: Chạy khi grant active. Mở grant, soạn hồ sơ nộp,
 * lưu kết quả vào agent_tasks.result, ghi log từng bước. Nếu grant bị thu hồi
 * giữa chừng → dừng ở bước kế tiếp, trạng thái failed_revoked, log rõ lý do.
 */
class RunAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    /**
     * @param  list<string>  $steps
     */
    public function __construct(
        public readonly string $taskId,
        public readonly array $steps = ['open_grant', 'extract_documents', 'draft_submission', 'finalize'],
    ) {}

    public function handle(GrantService $grants, GrantKeyStore $keys, LlmClient $llm): void
    {
        $task = AgentTask::find($this->taskId);

        if (! $task || in_array($task->status, [
            AgentTask::STATUS_DONE,
            AgentTask::STATUS_FAILED_REVOKED,
            AgentTask::STATUS_DENIED,
        ], true)) {
            return;
        }

        $task->forceFill(['status' => AgentTask::STATUS_RUNNING])->save();
        $task->appendLog('run.started', ['steps' => $this->steps]);

        $grant = $task->activeGrant();

        if (! $grant instanceof Grant || ! $grant->isActive()) {
            $this->failRevoked($task, $this->unavailableReason($task));

            return;
        }

        // Mở grant (đọc K_g, giải bundle trong bộ nhớ). Ghi audit grant.opened.
        try {
            $opened = $grants->open($grant);
        } catch (Throwable) {
            $this->failRevoked($task, 'grant_unavailable');

            return;
        }

        $documents = is_array($opened['documents'] ?? null) ? $opened['documents'] : [];

        $task->appendLog('run.grant_opened', [
            'grant_id' => $grant->getKey(),
            'document_count' => count($documents),
        ]);

        foreach ($this->steps as $index => $step) {
            // Kiểm tra grant + task còn hiệu lực trước mỗi bước (FR-AGENT-03).
            $task->refresh();

            if ($task->status !== AgentTask::STATUS_RUNNING) {
                $task->appendLog('run.stopped', [
                    'step' => $step,
                    'reason' => 'task_status_'.$task->status,
                ]);

                return;
            }

            $key = $keys->get($grant);
            if ($key === null) {
                $this->failRevoked($task, 'grant_key_shredded', $step);

                return;
            }

            $grant->refresh();
            if (! $grant->isActive()) {
                $this->failRevoked($task, 'grant_not_active', $step, $grant->status);

                return;
            }

            usleep(max(0, (int) config('trustgate.agent_step_delay_ms', 200)) * 1000);

            $task->appendLog('run.step', [
                'step' => $step,
                'index' => $index,
                'total' => count($this->steps),
                'at' => now()->toIso8601String(),
            ]);
        }

        // Chốt chặn cuối: grant có thể bị thu hồi ngay trước khi lưu kết quả.
        $task->refresh();
        $grant->refresh();

        if ($task->status !== AgentTask::STATUS_RUNNING || ! $grant->isActive() || $keys->get($grant) === null) {
            $this->failRevoked($task, 'grant_revoked_before_save', 'finalize');

            return;
        }

        $submission = $this->composeSubmission($task, $llm, $documents);

        $task->forceFill([
            'status' => AgentTask::STATUS_DONE,
            'result' => [
                'grant_id' => $grant->getKey(),
                'document_count' => count($documents),
                'documents' => array_values(array_map(fn (array $document) => [
                    'doc_id' => $document['doc_id'] ?? null,
                    'doc_type' => $document['doc_type'] ?? null,
                    'name' => $document['name'] ?? null,
                ], $documents)),
                'submission' => $submission['summary'],
                'mode' => $submission['mode'],
                'extracted_fields' => $this->extractedFields($documents),
                'opened_at' => $opened['opened_at'] ?? null,
                'composed_at' => now()->toIso8601String(),
            ],
        ])->save();

        $task->appendLog('run.completed', [
            'document_count' => count($documents),
            'mode' => $submission['mode'],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $documents
     * @return array{summary: string, mode: string}
     */
    private function composeSubmission(AgentTask $task, LlmClient $llm, array $documents): array
    {
        $mode = (string) config('trustgate.llm_driver', 'mock');

        try {
            return [
                'summary' => $llm->summarize($documents),
                'mode' => $mode,
            ];
        } catch (Throwable $exception) {
            // Không chặn demo khi provider LLM lỗi; ghi log không chứa plaintext.
            report($exception);

            return [
                'summary' => (new MockLlmClient)->summarize($documents),
                'mode' => 'fallback',
            ];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $documents
     * @return array<string, string>
     */
    private function extractedFields(array $documents): array
    {
        $fields = [];

        foreach ($documents as $document) {
            $docType = is_string($document['doc_type'] ?? null) ? $document['doc_type'] : 'unknown';
            $content = is_string($document['content_b64'] ?? null)
                ? base64_decode($document['content_b64'], true)
                : false;

            if (is_string($content) && $content !== '') {
                $fields[$docType] = Str::limit($content, 200);
            }
        }

        return $fields;
    }

    private function failRevoked(AgentTask $task, string $reason, ?string $step = null, ?string $grantStatus = null): void
    {
        $task->forceFill(['status' => AgentTask::STATUS_FAILED_REVOKED])->save();

        $task->appendLog('run.stopped', array_filter([
            'step' => $step,
            'reason' => $reason,
            'grant_status' => $grantStatus,
            'at' => now()->toIso8601String(),
        ], fn ($value) => $value !== null));
    }

    private function unavailableReason(AgentTask $task): string
    {
        $latest = $task->grants()->latest('created_at')->first();

        return match ($latest?->status) {
            Grant::STATUS_REVOKED => 'grant_revoked',
            Grant::STATUS_DENIED => 'grant_denied',
            Grant::STATUS_EXPIRED => 'grant_expired',
            default => 'no_active_grant',
        };
    }
}
