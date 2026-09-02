<?php

namespace App\Services\Mining;

use App\Models\Candidate;
use App\Models\User;
use Illuminate\Support\Collection;

class CandidateDeduplicator
{
    public function __construct(
        private readonly CandidateNormalizer $normalizer
    ) {
    }

    /**
     * @param  list<array<string, mixed>>  $incoming
     * @return array{unique:list<array<string,mixed>>,duplicates:int}
     */
    public function dedupe(User $user, array $incoming): array
    {
        $existing = Candidate::query()
            ->ownedBy($user)
            ->with(['skills', 'sources'])
            ->get();

        $unique = [];
        $duplicates = 0;
        $seen = [];

        foreach ($incoming as $record) {
            $key = $this->fingerprint($record);
            $match = $this->findExisting($existing, $record) ?? ($seen[$key] ?? null);

            if ($match !== null) {
                $duplicates++;
                if (is_array($match)) {
                    $seen[$key] = $this->mergeRecords($match, $record);
                } else {
                    $this->mergeIntoModel($match, $record);
                }

                continue;
            }

            $unique[] = $record;
            $seen[$key] = $record;
        }

        return ['unique' => array_values($seen), 'duplicates' => $duplicates];
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
    public function mergeIntoModel(Candidate $candidate, array $record): void
    {
        foreach (['designation', 'company', 'location', 'professional_email', 'phone', 'profile_url', 'portfolio_url', 'industry'] as $field) {
            if (blank($candidate->{$field}) && filled($record[$field] ?? null)) {
                $candidate->{$field} = $record[$field];
            }
        }
        $candidate->duplicate_status = 'merged';
        $candidate->save();

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
