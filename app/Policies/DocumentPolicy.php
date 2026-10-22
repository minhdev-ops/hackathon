<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    /**
     * NFR-SEC-03: mọi truy vấn theo owner_id qua Policy; test IDOR cho từng endpoint có {id}.
     */
    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function view(User $user, Document $document): bool
    {
        return $document->owner_id === $user->getKey();
    }

    public function download(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    public function delete(User $user, Document $document): bool
    {
        return $document->owner_id === $user->getKey();
    }
}
