<?php

namespace App\Models;

use Database\Factories\ChangeRequestFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChangeRequest extends Model
{
    /** @use HasFactory<ChangeRequestFactory> */
    use HasFactory, HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_EXECUTED = 'executed';

    public const STATUS_REJECTED = 'rejected';

    public const TYPE_ROLE_GRANT = 'role_grant';

    public const TYPE_ROLE_REVOKE = 'role_revoke';

    public const TYPE_PLAYBOOK_UPDATE = 'playbook_update';

    public const TYPE_AGENT_ALLOWLIST_UPDATE = 'agent_allowlist_update';

    public const TYPE_QUORUM_THRESHOLD_UPDATE = 'quorum_threshold_update';

    protected $fillable = [
        'type',
        'payload',
        'requested_by',
        'status',
        'required_approvals',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'executed_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ChangeRequestApproval::class, 'change_request_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function approvedCount(): int
    {
        return $this->approvals()->where('decision', 'approve')->count();
    }

    public function rejectedCount(): int
    {
        return $this->approvals()->where('decision', 'reject')->count();
    }
}
