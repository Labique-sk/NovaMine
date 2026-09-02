<?php

namespace App\Policies;

use App\Models\MiningJob;
use App\Models\User;

class MiningJobPolicy
{
    public function view(User $user, MiningJob $miningJob): bool
    {
        return $miningJob->user_id === $user->id;
    }

    public function update(User $user, MiningJob $miningJob): bool
    {
        return $miningJob->user_id === $user->id;
    }
}
