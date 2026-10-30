<?php

namespace App\Services\Sources;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class PeopleDataLabsAdapter implements SourceAdapterInterface
{
    private const ENDPOINT = 'https://api.peopledatalabs.com/v5/person/search';

    /** Only request the professional fields NovaMine uses. */
    private const DATA_FIELDS = 'id,full_name,first_name,last_name,job_title,job_company_name,job_company_industry,industry,location_name,location_locality,location_region,location_country,linkedin_url,github_url,work_email,skills,inferred_years_experience,summary';

    public function name(): string
    {
        return 'People Data Labs';
    }

    public function collect(array $criteria): array
    {
        $apiKey = trim((string) config('services.pdl.api_key'));
        $role = trim((string) ($criteria['role'] ?? ''));
        if (! config('services.pdl.enabled', false) || $apiKey === '' || $role === '') {
            return [];
        }

        $must = [['match' => ['job_title' => $role]]];
        $location = trim((string) ($criteria['location'] ?? ''));
        if ($location !== '') {
            $must[] = ['match' => ['location_name' => $location]];
        }

        // Skills add relevance without requiring every listed skill to match.
        $skills = $criteria['skills'] ?? [];
        if (is_string($skills)) {
            $skills = preg_split('/[,;]+/', $skills) ?: [];
        }
        $skillTerms = array_slice(array_values(array_filter(array_map(
            static fn ($skill) => trim((string) $skill),
            (array) $skills
        ))), 0, 5);
        $should = array_map(static fn (string $skill) => ['match' => ['skills' => $skill]], $skillTerms);

        $limit = min(20, max(1, (int) config('services.pdl.result_limit', 10)));

        try {
            $response = Http::connectTimeout(4)
                ->timeout(20)
                ->withHeaders([
                    'X-Api-Key' => $apiKey,
                    'Accept' => 'application/json',
                ])
                ->post(self::ENDPOINT, [
                    'query' => ['bool' => array_filter([
                        'must' => $must,
                        'should' => $should ?: null,
                    ])],
                    'size' => $limit,
                    'dataset' => 'resume',
                    'titlecase' => true,
                    'data_include' => self::DATA_FIELDS,
                ]);

            if (! $response->successful()) {
                Log::warning('People Data Labs search failed', ['status' => $response->status()]);

                return [];
            }

            $records = [];
            foreach (array_slice((array) $response->json('data', []), 0, $limit) as $person) {
                if (! is_array($person)) {
                    continue;
                }

                $name = trim((string) ($person['full_name'] ?? ''));
                if ($name === '') {
                    $name = trim((string) ($person['first_name'] ?? '').' '.(string) ($person['last_name'] ?? ''));
                }
                if ($name === '') {
                    continue;
                }

                $profileUrl = $this->profileUrl($person);
                $personSkills = $person['skills'] ?? [];
                if (is_string($personSkills)) {
                    $personSkills = preg_split('/[,;]+/', $personSkills) ?: [];
                }

                $records[] = [
                    'name' => $name,
                    'designation' => $person['job_title'] ?? null,
                    'company' => $person['job_company_name'] ?? null,
                    'experience' => $person['inferred_years_experience'] ?? null,
                    'industry' => $person['job_company_industry'] ?? $person['industry'] ?? null,
                    'skills' => array_values(array_filter((array) $personSkills, 'is_string')),
                    'location' => $person['location_name'] ?? implode(', ', array_filter([
                        $person['location_locality'] ?? null,
                        $person['location_region'] ?? null,
                        $person['location_country'] ?? null,
                    ])),
                    'email' => $person['work_email'] ?? null,
                    'profile_url' => $profileUrl,
                    'source_url' => $profileUrl,
                    'source_name' => $this->name(),
                    'source_snippet' => $person['summary'] ?? null,
                    'candidate_type' => (string) ($criteria['candidate_type'] ?? 'Professional'),
                    'is_synthetic' => false,
                ];
            }

            return $records;
        } catch (Throwable $exception) {
            // Do not log request headers, response bodies, or exception details that may contain credentials.
            Log::warning('People Data Labs adapter request failed', ['exception' => $exception::class]);

            return [];
        }
    }

    /** @param array<string, mixed> $person */
    private function profileUrl(array $person): ?string
    {
        foreach (['linkedin_url', 'github_url'] as $field) {
            $url = trim((string) ($person[$field] ?? ''));
            if ($url !== '') {
                return str_starts_with($url, 'http') ? $url : 'https://'.$url;
            }
        }

        return null;
    }
}
