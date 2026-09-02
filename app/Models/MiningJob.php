<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'query',
    'filters',
    'status',
    'stage',
    'progress',
    'total_found',
    'processed',
    'verified',
    'duplicates',
    'started_at',
    'completed_at',
    'error_message',
])]
class MiningJob extends Model
{
    public const STATUSES = ['queued', 'running', 'validating', 'completed', 'failed', 'cancelled'];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'progress' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, ['queued', 'running', 'validating'], true);
    }
}
