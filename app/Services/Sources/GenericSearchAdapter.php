<?php

namespace App\Services\Sources;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenericSearchAdapter implements SourceAdapterInterface
{
    public function name(): string
    {
        return 'GitHub Public API';
    }

    public function collect(array $criteria): array
    {
        $roleAndIndustry = strtolower((string) ($criteria['role'] ?? '') . ' ' . (string) ($criteria['industry'] ?? ''));
        if (! preg_match('/developer|engineer|software|programmer|devops|cloud|data|cyber|security|technical|it support|system administrator|frontend|backend|full.?stack|machine learning|\bai\b|\bqa\b tester/i', $roleAndIndustry)) {
            return [];
        }
        $location = trim((string) ($criteria['location'] ?? ''));
        $skills = $criteria['skills'] ?? [];
        if (is_string($skills)) {
            $skills = preg_split('/[,;]+/', $skills) ?: [];
        }
        $skill = trim((string) ($skills[0] ?? 'Python'));
        $city = trim(explode(',', $location)[0]);

        $query = implode(' ', array_filter(['type:user', $city !== '' ? 'location:'.$city : null, $skill !== '' ? 'language:'.$skill : null]));

        try {
            $headers = [
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'NovaMine/1.0 (permitted public API client)',
            ];
            if ($token = config('services.github.token')) {
                $headers['Authorization'] = 'Bearer '.$token;
            }

            $response = Http::timeout(8)
                ->withHeaders($headers)
                ->get('https://api.github.com/search/users', [
                    'q' => $query,
                    'per_page' => 8,
                ]);

            if (! $response->successful()) {
                Log::info('GitHub search unavailable', ['status' => $response->status()]);

                return [];
            }

            $items = $response->json('items') ?? [];
            $records = [];

            foreach (array_slice($items, 0, 8) as $item) {
                $login = (string) ($item['login'] ?? '');
                if ($login === '') {
                    continue;
                }

                $profile = Http::timeout(8)->withHeaders($headers)->get('https://api.github.com/users/'.$login);
                $user = $profile->successful() ? $profile->json() : [];
                $name = $user['name'] ?? $login;
                $email = $user['email'] ?? null;
                $repositories = Http::timeout(8)->withHeaders($headers)->get('https://api.github.com/users/'.$login.'/repos', [
                    'per_page' => 50,
                    'sort' => 'updated',
                ]);
                $observedSkills = [];
                if ($repositories->successful()) {
                    foreach ((array) $repositories->json() as $repository) {
                        if (! empty($repository['language'])) {
                            $observedSkills[] = (string) $repository['language'];
                        }
                    }
                }
                $observedSkills = array_values(array_unique($observedSkills));
                $blog = $user['blog'] ?? null;
                $htmlUrl = $user['html_url'] ?? ($item['html_url'] ?? null);

                $records[] = [
                    'name' => $name,
                    'designation' => 'Public GitHub developer profile (role not confirmed)',
                    'company' => $user['company'] ?? null,
                    'experience' => null,
                    'industry' => 'Technology',
                    'skills' => $observedSkills,
                    'education' => [],
                    'location' => $user['location'] ?? null,
                    'email' => $email,
                    'phone' => null,
                    'profile_url' => $htmlUrl,
                    'portfolio_url' => $blog ?: null,
                    'source_url' => $htmlUrl,
                    'source_name' => $this->name(),
                    'candidate_type' => (string) ($criteria['candidate_type'] ?? 'Professional'),
                    'is_synthetic' => false,
                ];
            }

            return $records;
        } catch (Throwable $e) {
            Log::info('GitHub adapter skipped', ['error' => $e->getMessage()]);

            return [];
        }
    }
}
