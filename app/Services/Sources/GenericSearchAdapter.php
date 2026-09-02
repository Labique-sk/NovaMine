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
        $location = (string) ($criteria['location'] ?? 'Pune');
        $skills = $criteria['skills'] ?? ['Python'];
        if (is_string($skills)) {
            $skills = preg_split('/[,;]+/', $skills) ?: [];
        }
        $skill = trim((string) ($skills[0] ?? 'Python'));
        $city = trim(explode(',', $location)[0]);

        $query = sprintf('location:%s language:%s type:user', $city, $skill);

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
                $blog = $user['blog'] ?? null;
                $htmlUrl = $user['html_url'] ?? ($item['html_url'] ?? null);

                $records[] = [
                    'name' => $name,
                    'designation' => (string) ($criteria['role'] ?? 'Software Engineer'),
                    'company' => $user['company'] ?? null,
                    'experience' => null,
                    'industry' => (string) ($criteria['industry'] ?? 'Technology'),
                    'skills' => array_values(array_filter(array_map('trim', (array) $skills))),
                    'education' => [],
                    'location' => $user['location'] ?? $location,
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
