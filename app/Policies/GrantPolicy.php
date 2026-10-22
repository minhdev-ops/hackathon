<?php

namespace App\Policies;

use App\Models\Grant;
use App\Models\User;

/**
 * NFR-SEC-03: every grant action is scoped to owner_id, no IDOR.
 */
class GrantPolicy
{
    public function view(User $user, Grant $grant): bool
    {
        return $grant->isOwnedBy($user->id);
    }

    public function approve(User $user, Grant $grant): bool
    {
        return $grant->isOwnedBy($user->id);
    }

    public function deny(User $user, Grant $grant): bool
    {
        return $grant->isOwnedBy($user->id);
    }

    public function revoke(User $user, Grant $grant): bool
    {
        return $grant->isOwnedBy($user->id);
    }
}
