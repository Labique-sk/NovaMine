<?php

namespace App\Policies;

use App\Models\Candidate;
use App\Models\User;

class CandidatePolicy
{
    public function view(User $user, Candidate $candidate): bool
    {
        return $candidate->user_id === $user->id;
    }

    public function update(User $user, Candidate $candidate): bool
    {
        return $candidate->user_id === $user->id;
    }

    public function delete(User $user, Candidate $candidate): bool
    {
        return $candidate->user_id === $user->id;
    }
}
