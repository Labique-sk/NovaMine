<?php

namespace App\Services\Sources;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class BraveWebSearchAdapter implements SourceAdapterInterface
{
    private const BLOCKED_HOSTS = [
        'linkedin.com', 'www.linkedin.com',
        'indeed.com', 'www.indeed.com',
        'naukri.com', 'www.naukri.com',
        'glassdoor.com', 'www.glassdoor.com',
    ];

    public function name(): string
    {
        return 'Brave Web Search';
    }

    public function collect(array $criteria): array
    {
        $apiKey = (string) config('services.brave_search.key');
        $role = trim((string) ($criteria['role'] ?? ''));
        if ($apiKey === '' || $role === '') {
            return [];
        }

        $location = trim((string) ($criteria['location'] ?? ''));
        $industry = trim((string) ($criteria['industry'] ?? ''));
        $keywords = trim((string) ($criteria['keywords'] ?? ''));
        $skills = $criteria['skills'] ?? [];
        if (is_string($skills)) {
            $skills = preg_split('/[,;]+/', $skills) ?: [];
        }
        $skills = array_values(array_filter(array_map('trim', (array) $skills)));

        $queryParts = ['"' . $role . '"'];
        if ($industry !== '') {
            $queryParts[] = '"' . $industry . '"';
        }
        if ($location !== '') {
            $queryParts[] = '"' . $location . '"';
        }
        if ($keywords !== '') {
            $queryParts[] = $keywords;
        }
        if ($skills !== []) {
            $queryParts[] = implode(' ', array_slice($skills, 0, 5));
        }
        $queryParts[] = '(profile OR portfolio OR resume OR CV OR biography)';
        $query = implode(' ', $queryParts);

        try {
            $response = Http::timeout(12)
                ->retry(2, 300)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-Subscription-Token' => $apiKey,
                    'User-Agent' => 'NovaMine/2.0 (public web search integration)',
                ])
                ->get('https://api.search.brave.com/res/v1/web/search', [
                    'q' => $query,
                    'count' => min(20, max(5, (int) config('services.brave_search.result_limit', 10))),
                    'safesearch' => 'moderate',
                ]);

            if (! $response->successful()) {
                Log::warning('Brave web search request failed', ['status' => $response->status()]);
                return [];
            }

            $results = $response->json('web.results', []);
            $records = [];
            foreach (array_slice(is_array($results) ? $results : [], 0, 20) as $result) {
                $url = (string) ($result['url'] ?? '');
                $title = trim((string) ($result['title'] ?? ''));
                $description = trim((string) ($result['description'] ?? ''));

                if (! filter_var($url, FILTER_VALIDATE_URL) || $title === '') {
                    continue;
                }

                $host = strtolower((string) parse_url($url, PHP_URL_HOST));
                if ($this->isBlockedHost($host)) {
                    continue;
                }

                $personName = $this->personNameFromTitle($title);
                if ($personName === '') {
                    // Do not manufacture candidate identities from generic page titles.
                    continue;
                }

                $matchedSkills = [];
                $haystack = mb_strtolower($title . ' ' . $description);
                foreach ($skills as $skill) {
                    if ($skill !== '' && str_contains($haystack, mb_strtolower($skill))) {
                        $matchedSkills[] = $skill;
                    }
                }

                $records[] = [
                    'name' => $personName,
                    'designation' => $title,
                    'company' => null,
                    'experience' => null,
                    'industry' => $industry ?: null,
                    'skills' => $matchedSkills,
                    'education' => [],
                    'location' => null,
                    'email' => null,
                    'phone' => null,
                    'profile_url' => $url,
                    'portfolio_url' => null,
                    'source_url' => $url,
                    'source_name' => $this->name(),
                    'source_snippet' => $description,
                    'candidate_type' => (string) ($criteria['candidate_type'] ?? 'Professional'),
                    'is_synthetic' => false,
                ];
            }

            return $records;
        } catch (Throwable $e) {
            Log::warning('Brave web search adapter failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function isBlockedHost(string $host): bool
    {
        foreach (self::BLOCKED_HOSTS as $blocked) {
            if ($host === $blocked || str_ends_with($host, '.' . $blocked)) {
                return true;
            }
        }

        return false;
    }

    private function personNameFromTitle(string $title): string
    {
        $parts = preg_split('/\s+[|–—-]\s+/', $title) ?: [];
        $candidate = trim((string) ($parts[0] ?? $title));
        $candidate = preg_replace('/^(profile|resume|cv)\s+(of|for)\s+/i', '', $candidate) ?? $candidate;
        $candidate = preg_replace('/\b(profile|portfolio|resume|curriculum vitae|cv|official website)\b/i', '', $candidate) ?? $candidate;
        $candidate = trim($candidate, " \t\n\r\0\x0B|–—-");

        // Avoid treating generic page titles and job advertisements as people.
        if (preg_match('/\b(jobs?|careers?|vacancies|hiring|job description|apply now)\b/i', $candidate)) {
            return '';
        }

        $words = preg_split('/\s+/', $candidate) ?: [];
        if (count($words) < 2 || count($words) > 6 || mb_strlen($candidate) > 90) {
            return '';
        }

        return $candidate;
    }
}
