<?php

namespace Database\Factories;

use App\Models\ChangeRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChangeRequest>
 */
class ChangeRequestFactory extends Factory
{
    protected $model = ChangeRequest::class;

    public function definition(): array
    {
        return [
            'type' => ChangeRequest::TYPE_ROLE_GRANT,
            'payload' => [
                'email' => 'target@trustgate.test',
                'role' => 'admin',
            ],
            'requested_by' => User::factory()->state(['role' => 'admin']),
            'status' => ChangeRequest::STATUS_PENDING,
            'required_approvals' => 2,
        ];
    }

    public function executed(): static
    {
        return $this->state(fn () => [
            'status' => ChangeRequest::STATUS_EXECUTED,
            'executed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => ChangeRequest::STATUS_REJECTED]);
    }
}
