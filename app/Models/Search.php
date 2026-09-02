<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'mining_job_id', 'query', 'filters', 'result_count', 'status', 'duration_ms'])]
class Search extends Model
{
    protected function casts(): array
    {
        return ['filters' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function miningJob(): BelongsTo
    {
        return $this->belongsTo(MiningJob::class);
    }
}
