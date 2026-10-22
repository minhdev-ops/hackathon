<?php

namespace App\Models;

use Database\Factories\AgentTaskFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentTask extends Model
{
    /** @use HasFactory<AgentTaskFactory> */
    use HasFactory, HasUuids;

    public const STATUS_PROPOSING = 'proposing';

    public const STATUS_PROPOSAL_READY = 'proposal_ready';

    public const STATUS_AWAITING_APPROVAL = 'awaiting_approval';

    public const STATUS_RUNNING = 'running';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED_REVOKED = 'failed_revoked';

    public const STATUS_DENIED = 'denied';

    protected $fillable = [
        'owner_id',
        'agent_id',
        'playbook_id',
        'instruction',
        'status',
        'proposal',
        'result',
        'log',
    ];

    protected function casts(): array
    {
        return [
            'proposal' => 'array',
            'result' => 'array',
            'log' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function playbook(): BelongsTo
    {
        return $this->belongsTo(Playbook::class);
    }

    public function grants(): HasMany
    {
        return $this->hasMany(Grant::class, 'task_id');
    }

    public function activeGrant(): ?Grant
    {
        return $this->grants()
            ->where('status', Grant::STATUS_ACTIVE)
            ->latest('created_at')
            ->first();
    }

    /**
     * Append one line to the task log (Agent Console reads this list).
     *
     * @param  array<string, mixed>  $context
     */
    public function appendLog(string $event, array $context = []): void
    {
        $entry = [
            'step' => $event,
            'at' => now()->toIso8601String(),
        ];

        // Never let business context overwrite the event name or timestamp.
        foreach ($context as $key => $value) {
            if ($key === 'step' || $key === 'at') {
                continue;
            }

            $entry[$key] = $value;
        }

        $log = $this->log ?? [];
        $log[] = $entry;

        $this->forceFill(['log' => $log])->save();
    }
}
