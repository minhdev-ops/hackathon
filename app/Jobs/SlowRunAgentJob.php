<?php

namespace App\Jobs;

use App\Models\AgentTask;
use App\Models\Grant;
use App\Services\GrantKeyStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job chậm dùng cho demo FR-DEMO-04: mỗi bước ngủ 2 giây và ghi log vào
 * AgentTask để Agent Console hiển thị theo thời gian thực. Dừng ngay khi
 * grant bị thu hồi (task chuyển failed_revoked).
 */
class SlowRunAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        private readonly string $taskId,
        private readonly int $steps = 6,
        private readonly int $sleepSeconds = 2,
    ) {}

    public function handle(GrantKeyStore $keys): void
    {
        $task = AgentTask::find($this->taskId);
        if (! $task) {
            return;
        }

        $grant = $task->activeGrant();
        if (! $grant) {
            $task->forceFill(['status' => AgentTask::STATUS_FAILED_REVOKED])->save();
            $task->appendLog('slow_run.aborted', ['reason' => 'no_active_grant']);

            return;
        }

        for ($i = 1; $i <= $this->steps; $i++) {
            // Nếu Owner thu hồi giữa chừng → agent hỏng ngay.
            $task->refresh();
            if ($task->status !== AgentTask::STATUS_RUNNING) {
                $task->appendLog('slow_run.stopped', [
                    'step' => $i,
                    'reason' => 'task_status_'.$task->status,
                ]);

                return;
            }

            $key = $keys->get($grant);
            if ($key === null) {
                $task->forceFill(['status' => AgentTask::STATUS_FAILED_REVOKED])->save();
                $task->appendLog('slow_run.stopped', [
                    'step' => $i,
                    'reason' => 'grant_key_shredded',
                ]);

                return;
            }

            $task->appendLog('slow_run.step', [
                'index' => $i,
                'total' => $this->steps,
            ]);

            if ($this->sleepSeconds > 0) {
                usleep($this->sleepSeconds * 1_000_000);
            }
        }

        $task->forceFill(['status' => AgentTask::STATUS_DONE])->save();
        $task->appendLog('slow_run.completed', ['steps' => $this->steps]);
    }
}
