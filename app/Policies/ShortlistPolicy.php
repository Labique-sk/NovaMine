<?php

namespace App\Policies;

use App\Models\Shortlist;
use App\Models\User;

class ShortlistPolicy
{
    public function view(User $user, Shortlist $shortlist): bool
    {
        return $shortlist->user_id === $user->id;
    }

    public function update(User $user, Shortlist $shortlist): bool
    {
        return $shortlist->user_id === $user->id;
    }

    public function delete(User $user, Shortlist $shortlist): bool
    {
        return $shortlist->user_id === $user->id;
    }
}
