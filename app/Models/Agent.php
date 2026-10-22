<?php

namespace App\Models;

use Database\Factories\AgentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    /** @use HasFactory<AgentFactory> */
    use HasFactory, HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISABLED = 'disabled';

    protected $fillable = [
        'name',
        'client_id',
        'allowed_playbooks',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'allowed_playbooks' => 'array',
        ];
    }

    public function grants(): HasMany
    {
        return $this->hasMany(Grant::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(AgentTask::class);
    }

    public function allowsPlaybook(string $slug): bool
    {
        $allowed = $this->allowed_playbooks ?? [];

        return $allowed === [] || in_array($slug, $allowed, true);
    }
}
