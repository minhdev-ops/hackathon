<?php

namespace App\Policies;

use App\Models\AgentTask;
use App\Models\User;

/**
 * NFR-SEC-03: task chỉ được xem/sửa bởi Owner sở hữu, không IDOR.
 */
class TaskPolicy
{
    public function view(User $user, AgentTask $task): bool
    {
        return $task->owner_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }
}
