<?php

namespace App\Services\Sources;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class UserProvidedUrlAdapter implements SourceAdapterInterface
{
    public function name(): string
    {
        return 'User Provided URL';
    }

    public function collect(array $criteria): array
    {
        $urls = [];
        foreach (['profile_url', 'source_url', 'keywords'] as $key) {
            $value = $criteria[$key] ?? null;
            if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
                $urls[] = $value;
            }
        }

        $records = [];
        foreach (array_unique($urls) as $url) {
            if (! $this->isPermittedPublicUrl($url)) {
                continue;
            }

            try {
                $response = Http::timeout(6)
                    ->withHeaders(['User-Agent' => 'NovaMine/1.0 (public URL accessibility check)'])
                    ->head($url);

                if (! $response->successful()) {
                    continue;
                }

                $host = parse_url($url, PHP_URL_HOST) ?: 'public-source';
                $records[] = [
                    'name' => 'Public profile from '.$host,
                    'designation' => (string) ($criteria['role'] ?? null),
                    'company' => null,
                    'experience' => null,
                    'industry' => (string) ($criteria['industry'] ?? null),
                    'skills' => [],
                    'education' => [],
                    'location' => (string) ($criteria['location'] ?? null),
                    'email' => null,
                    'phone' => null,
                    'profile_url' => $url,
                    'portfolio_url' => $url,
                    'source_url' => $url,
                    'source_name' => $this->name(),
                    'candidate_type' => (string) ($criteria['candidate_type'] ?? 'Professional'),
                    'is_synthetic' => false,
                ];
            } catch (Throwable $e) {
                Log::info('User URL skipped', ['url' => $url, 'error' => $e->getMessage()]);
            }
        }

        return $records;
    }

    private function isPermittedPublicUrl(string $url): bool
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }

        return true;
    }
}
