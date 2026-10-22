<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\AgentTask;
use App\Models\Playbook;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentTask>
 */
class AgentTaskFactory extends Factory
{
    protected $model = AgentTask::class;

    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'agent_id' => Agent::factory(),
            'playbook_id' => Playbook::factory(),
            'instruction' => 'Nộp hồ sơ thuê căn hộ A',
            'status' => AgentTask::STATUS_AWAITING_APPROVAL,
            'proposal' => [
                'required' => ['id_card', 'income_proof'],
                'optional' => ['rental_history'],
                'rationale' => 'Chủ nhà cần giấy tờ tuỳ thân và chứng minh thu nhập.',
            ],
            'log' => [],
        ];
    }

    public function awaitingApproval(): static
    {
        return $this->state(fn () => ['status' => AgentTask::STATUS_AWAITING_APPROVAL]);
    }

    public function running(): static
    {
        return $this->state(fn () => ['status' => AgentTask::STATUS_RUNNING]);
    }
}
