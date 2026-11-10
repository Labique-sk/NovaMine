<?php

namespace App\Services\Mining;

use App\Models\Candidate;
use App\Models\User;
use Illuminate\Support\Collection;

class CandidateDeduplicator
{
    public function __construct(
        private readonly CandidateNormalizer $normalizer
    ) {}

    /**
     * @param  list<array<string, mixed>>  $incoming
     * @return array{unique:list<array<string,mixed>>,duplicates:int,updated:list<array{candidate:Candidate,record:array<string,mixed>}>}
     */
    public function dedupe(User $user, array $incoming, ?int $refreshMiningJobId = null): array
    {
        $existing = Candidate::query()
            ->ownedBy($user)
            ->with(['skills', 'sources'])
            ->get();

        $unique = [];
        $duplicates = 0;
        $seen = [];
        $updated = [];

        foreach ($incoming as $record) {
            $key = $this->fingerprint($record);
            $match = null;
            if ($refreshMiningJobId !== null) {
                $recordName = $this->nameKey((string) ($record['name'] ?? ''));
                $match = $existing->first(fn (Candidate $candidate) => (int) $candidate->mining_job_id === $refreshMiningJobId
                    && $this->nameKey($candidate->name) === $recordName
                    && $this->isDuplicate($candidate, $record));
            }
            $match ??= $this->findExisting($existing, $record) ?? ($seen[$key] ?? null);

            if ($match !== null) {
                $duplicates++;
                if (is_array($match)) {
                    $seen[$key] = $this->mergeRecords($match, $record);
                } else {
                    $shouldRefresh = $refreshMiningJobId !== null && (int) $match->mining_job_id === $refreshMiningJobId;
                    $this->mergeIntoModel($match, $record, $shouldRefresh);
                    if ($shouldRefresh) {
                        $updated[$match->id] = ['candidate' => $match, 'record' => $record];
                    }
                }

                continue;
            }

            $unique[] = $record;
            $seen[$key] = $record;
        }

        return ['unique' => array_values($seen), 'duplicates' => $duplicates, 'updated' => array_values($updated)];
    }

    /**
     * @param  Collection<int, Candidate>  $existing
     * @param  array<string, mixed>  $record
     */
    public function findExisting(Collection $existing, array $record): ?Candidate
    {
        foreach ($existing as $candidate) {
            if ($this->isDuplicate($candidate, $record)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public function isDuplicate(Candidate $candidate, array $record): bool
    {
        $email = $this->normalizer->normalizeEmail($record['professional_email'] ?? null);
        $phone = $this->normalizer->normalizePhone($record['phone'] ?? null);
        $url = $this->normalizer->normalizeUrl($record['profile_url'] ?? null);

        if ($email && $candidate->professional_email && $email === $this->normalizer->normalizeEmail($candidate->professional_email)) {
            return true;
        }
        if ($phone && $candidate->phone && $phone === $this->normalizer->normalizePhone($candidate->phone)) {
            return true;
        }
        if ($url && $candidate->profile_url && rtrim($url, '/') === rtrim((string) $candidate->profile_url, '/')) {
            return true;
        }

        $nameA = $this->nameKey($candidate->name);
        $nameB = $this->nameKey((string) ($record['name'] ?? ''));
        if ($nameA !== '' && $nameA === $nameB) {
            if ($this->same($candidate->company, $record['company'] ?? null)) {
                return true;
            }
            if ($this->same($candidate->designation, $record['designation'] ?? null) && $this->same($candidate->location, $record['location'] ?? null)) {
                return true;
            }
            similar_text(strtolower((string) $candidate->company), strtolower((string) ($record['company'] ?? '')), $pct);
            if ($pct >= 85) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public function mergeIntoModel(Candidate $candidate, array $record, bool $replaceFilled = false): void
    {
        foreach (['designation', 'company', 'years_experience', 'location', 'professional_email', 'phone', 'profile_url', 'portfolio_url', 'industry', 'source_url', 'source_name', 'source_snippet', 'verification_status', 'email_validation_status', 'phone_validation_status', 'profile_validation_status'] as $field) {
            if (filled($record[$field] ?? null) && ($replaceFilled || blank($candidate->{$field}))) {
                $candidate->{$field} = $record[$field];
            }
        }
        if (in_array($record['employment_status'] ?? null, ['working', 'student', 'both'], true)
            && ($replaceFilled || $candidate->employment_status === 'unknown' || blank($candidate->employment_status))) {
            $candidate->employment_status = $record['employment_status'];
        }
        $candidate->duplicate_status = 'merged';
        $candidate->save();

        foreach ($record['skills'] ?? [] as $skill) {
            if (is_string($skill) && trim($skill) !== '') {
                $candidate->skills()->updateOrCreate(
                    ['name' => trim($skill)],
                    ['is_match' => false],
                );
            }
        }

        if (! empty($record['source_name'])) {
            $candidate->sources()->firstOrCreate([
                'source_name' => $record['source_name'],
                'source_url' => $record['source_url'] ?? null,
            ], ['collected_at' => now()]);
        }
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     * @return array<string, mixed>
     */
    private function mergeRecords(array $left, array $right): array
    {
        foreach (['designation', 'company', 'location', 'professional_email', 'phone', 'profile_url', 'portfolio_url'] as $field) {
            if (blank($left[$field] ?? null) && filled($right[$field] ?? null)) {
                $left[$field] = $right[$field];
            }
        }
        if (($left['employment_status'] ?? 'unknown') === 'unknown' && in_array($right['employment_status'] ?? null, ['working', 'student', 'both'], true)) {
            $left['employment_status'] = $right['employment_status'];
        }
        $left['skills'] = array_values(array_unique(array_merge($left['skills'] ?? [], $right['skills'] ?? [])));
        $left['duplicate_status'] = 'merged';

        return $left;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function fingerprint(array $record): string
    {
        $email = $this->normalizer->normalizeEmail($record['professional_email'] ?? null);
        $phone = $this->normalizer->normalizePhone($record['phone'] ?? null);
        $url = $this->normalizer->normalizeUrl($record['profile_url'] ?? null);
        $name = $this->nameKey((string) ($record['name'] ?? ''));

        return $email ?: ($phone ?: ($url ?: $name.'|'.strtolower((string) ($record['company'] ?? ''))));
    }

    private function nameKey(string $name): string
    {
        $name = preg_replace('/^\[demo\]\s+/i', '', trim($name)) ?? $name;

        return strtolower(preg_replace('/\s+/', ' ', $name) ?? $name);
    }

    private function same(mixed $a, mixed $b): bool
    {
        $a = strtolower(trim((string) $a));
        $b = strtolower(trim((string) $b));

        return $a !== '' && $a === $b;
    }
}
