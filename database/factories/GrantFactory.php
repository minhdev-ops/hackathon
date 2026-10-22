<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\AgentTask;
use App\Models\Grant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grant>
 */
class GrantFactory extends Factory
{
    protected $model = Grant::class;

    public function definition(): array
    {
        return [
            'task_id' => AgentTask::factory(),
            'owner_id' => User::factory(),
            'agent_id' => Agent::factory(),
            'purpose' => 'Nộp hồ sơ thuê nhà',
            'doc_ids' => [],
            'status' => Grant::STATUS_PENDING,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => Grant::STATUS_ACTIVE,
            'approved_at' => now(),
            'expires_at' => now()->addMinutes(15),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'status' => Grant::STATUS_REVOKED,
            'approved_at' => now()->subMinutes(5),
            'expires_at' => now()->addMinutes(10),
            'revoked_at' => now(),
        ]);
    }

    public function denied(): static
    {
        return $this->state(fn () => ['status' => Grant::STATUS_DENIED]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => Grant::STATUS_EXPIRED,
            'approved_at' => now()->subMinutes(20),
            'expires_at' => now()->subMinutes(5),
        ]);
    }
}
