<?php

namespace App\Policies;

use App\Models\ExportRecord;
use App\Models\User;

class ExportRecordPolicy
{
    public function view(User $user, ExportRecord $exportRecord): bool
    {
        return $exportRecord->user_id === $user->id;
    }
}
