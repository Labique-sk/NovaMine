<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'shortlist_id',
    'format',
    'record_count',
    'filters',
    'disk',
    'file_path',
    'status',
    'error_message',
])]
class ExportRecord extends Model
{
    protected $table = 'exports';

    protected function casts(): array
    {
        return ['filters' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shortlist(): BelongsTo
    {
        return $this->belongsTo(Shortlist::class);
    }
}
