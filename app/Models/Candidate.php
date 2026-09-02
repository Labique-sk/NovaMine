<?php

namespace App\Models;

use Database\Factories\CandidateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'mining_job_id',
    'name',
    'designation',
    'company',
    'years_experience',
    'industry',
    'location',
    'professional_email',
    'phone',
    'profile_url',
    'portfolio_url',
    'source_url',
    'source_name',
    'candidate_type',
    'verification_status',
    'email_validation_status',
    'phone_validation_status',
    'profile_validation_status',
    'duplicate_status',
    'match_score',
    'score_breakdown',
    'is_synthetic',
])]
class Candidate extends Model
{
    /** @use HasFactory<CandidateFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'score_breakdown' => 'array',
            'is_synthetic' => 'boolean',
            'years_experience' => 'integer',
            'match_score' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function miningJob(): BelongsTo
    {
        return $this->belongsTo(MiningJob::class);
    }

    public function skills(): HasMany
    {
        return $this->hasMany(CandidateSkill::class);
    }

    public function education(): HasMany
    {
        return $this->hasMany(CandidateEducation::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CandidateContact::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(CandidateSource::class);
    }

    public function validationResults(): HasMany
    {
        return $this->hasMany(ValidationResult::class);
    }

    public function shortlists(): BelongsToMany
    {
        return $this->belongsToMany(Shortlist::class, 'shortlist_candidates')->withTimestamps();
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function toSearchArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->designation,
            'company' => $this->company,
            'location' => $this->location,
            'experience' => $this->years_experience,
            'score' => $this->match_score,
            'status' => $this->uiStatus(),
            'email' => $this->professional_email,
            'phone' => $this->phone,
            'skills' => $this->relationLoaded('skills') ? $this->skills->pluck('name')->all() : [],
            'is_synthetic' => $this->is_synthetic,
            'source_name' => $this->source_name,
            'verification_status' => $this->verification_status,
        ];
    }

    public function uiStatus(): string
    {
        return $this->verification_status === 'verified' ? 'verified' : 'review';
    }
}
