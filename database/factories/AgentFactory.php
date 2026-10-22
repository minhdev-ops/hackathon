<?php

namespace Database\Factories;

use App\Models\Agent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agent>
 */
class AgentFactory extends Factory
{
    protected $model = Agent::class;

    public function definition(): array
    {
        return [
            'name' => 'TrustGate Agent',
            'client_id' => 'trustgate-agent-'.fake()->unique()->numerify('####'),
            'allowed_playbooks' => ['rental', 'clinic'],
            'status' => Agent::STATUS_ACTIVE,
        ];
    }
}
