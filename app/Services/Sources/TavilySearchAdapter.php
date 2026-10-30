<?php

namespace App\Services\Sources;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TavilySearchAdapter implements SourceAdapterInterface
{
    private const BLOCKED_HOSTS = [
        'linkedin.com', 'indeed.com', 'naukri.com', 'glassdoor.com',
    ];

    public function name(): string
    {
        return 'Tavily Web Search';
    }

    public function collect(array $criteria): array
    {
        $apiKey = (string) config('services.tavily.key');
        $role = trim((string) ($criteria['role'] ?? ''));
        if ($apiKey === '' || $role === '') {
            return [];
        }

        $queryParts = ['"'.$role.'"'];
        foreach (['industry', 'location', 'keywords'] as $field) {
            $value = trim((string) ($criteria[$field] ?? ''));
            if ($value !== '') {
                $queryParts[] = '"'.$value.'"';
            }
        }

        $skills = $criteria['skills'] ?? [];
        if (is_string($skills)) {
            $skills = preg_split('/[,;]+/', $skills) ?: [];
        }
        $skills = array_values(array_filter(array_map('trim', (array) $skills)));
        if ($skills !== []) {
            $queryParts[] = implode(' ', array_slice($skills, 0, 5));
        }
        $queryParts[] = '(profile OR portfolio OR resume OR CV OR biography)';

        try {
            $response = Http::timeout(20)
                ->retry(2, 300)
                ->withToken($apiKey)
                ->post('https://api.tavily.com/search', [
                    'query' => implode(' ', $queryParts),
                    'search_depth' => 'basic',
                    'topic' => 'general',
                    'max_results' => min(20, max(5, (int) config('services.tavily.result_limit', 10))),
                    'include_answer' => false,
                    'include_raw_content' => false,
                    'exclude_domains' => self::BLOCKED_HOSTS,
                ]);

            if (! $response->successful()) {
                Log::warning('Tavily web search request failed', ['status' => $response->status()]);

                return [];
            }

            $records = [];
            foreach (array_slice((array) $response->json('results', []), 0, 20) as $result) {
                $url = (string) ($result['url'] ?? '');
                $title = trim((string) ($result['title'] ?? ''));
                $snippet = trim((string) ($result['content'] ?? ''));

                if (! filter_var($url, FILTER_VALIDATE_URL) || $title === '') {
                    continue;
                }

                $host = strtolower((string) parse_url($url, PHP_URL_HOST));
                if ($this->isBlockedHost($host)) {
                    continue;
                }

                $name = $this->personNameFromTitle($title);
                if ($name === '') {
                    continue;
                }

                $haystack = mb_strtolower($title.' '.$snippet);
                $matchedSkills = array_values(array_filter(
                    $skills,
                    fn (string $skill) => str_contains($haystack, mb_strtolower($skill))
                ));

                $records[] = [
                    'name' => $name,
                    'designation' => $title,
                    'industry' => trim((string) ($criteria['industry'] ?? '')) ?: null,
                    'skills' => $matchedSkills,
                    'location' => null,
                    'profile_url' => $url,
                    'source_url' => $url,
                    'source_name' => $this->name(),
                    'source_snippet' => $snippet,
                    'candidate_type' => (string) ($criteria['candidate_type'] ?? 'Professional'),
                    'is_synthetic' => false,
                ];
            }

            return $records;
        } catch (Throwable $e) {
            Log::warning('Tavily web search adapter failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function isBlockedHost(string $host): bool
    {
        foreach (self::BLOCKED_HOSTS as $blocked) {
            if ($host === $blocked || str_ends_with($host, '.'.$blocked)) {
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

        if (preg_match('/\b(jobs?|careers?|vacancies|hiring|job description|apply now|resume|curriculum vitae|profile|portfolio|biography|engineer|developer|scientist|analyst|architect|designer|consultant|manager|director|intern|student|software|machine learning|artificial intelligence|data science)\b/i', $candidate)) {
            return '';
        }

        $words = preg_split('/\s+/', $candidate) ?: [];
        if (count($words) < 2 || count($words) > 6 || mb_strlen($candidate) > 90) {
            return '';
        }

        return $candidate;
    }
}
