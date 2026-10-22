<?php

namespace App\Models;

use Database\Factories\GrantFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Grant extends Model
{
    /** @use HasFactory<GrantFactory> */
    use HasFactory, HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_DENIED = 'denied';

    protected $fillable = [
        'task_id',
        'owner_id',
        'agent_id',
        'purpose',
        'doc_ids',
        'status',
        'approved_at',
        'expires_at',
        'revoked_at',
        'bundle_path',
        'bundle_iv',
    ];

    protected function casts(): array
    {
        return [
            'doc_ids' => 'array',
            'approved_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(AgentTask::class, 'task_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * A grant is openable only while it is active and its TTL has not run out.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    public function remainingSeconds(): int
    {
        if ($this->expires_at === null) {
            return 0;
        }

        return max(0, $this->expires_at->getTimestamp() - now()->getTimestamp());
    }

    public function isOwnedBy(int $userId): bool
    {
        return $this->owner_id === $userId;
    }

    /**
     * @return list<string>
     */
    public function docIds(): array
    {
        return array_values($this->doc_ids ?? []);
    }

    public function shortId(): string
    {
        return Str::substr($this->id, 0, 8);
    }
}
