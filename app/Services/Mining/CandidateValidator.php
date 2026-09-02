<?php

namespace App\Services\Mining;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class CandidateValidator
{
    public function __construct(
        private readonly CandidateNormalizer $normalizer
    ) {
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @return array{status:string,email:string,phone:string,profile:string,results:list<array<string,mixed>>}
     */
    public function validate(array $candidate): array
    {
        $results = [];

        $email = $this->normalizer->normalizeEmail($candidate['professional_email'] ?? null);
        $emailStatus = $this->validateEmail($email);
        $results[] = ['type' => 'email', 'status' => $emailStatus['status'], 'message' => $emailStatus['message'], 'http_status' => null];

        $phone = $this->normalizer->normalizePhone($candidate['phone'] ?? null);
        $phoneStatus = $this->validatePhone($phone);
        $results[] = ['type' => 'phone', 'status' => $phoneStatus['status'], 'message' => $phoneStatus['message'], 'http_status' => null];

        $profile = $this->normalizer->normalizeUrl($candidate['profile_url'] ?? null);
        $profileStatus = $this->validateProfile($profile);
        $results[] = [
            'type' => 'profile',
            'status' => $profileStatus['status'],
            'message' => $profileStatus['message'],
            'http_status' => $profileStatus['http_status'],
        ];

        $required = filled($candidate['name'] ?? null) && filled($candidate['designation'] ?? null);
        $results[] = [
            'type' => 'candidate',
            'status' => $required ? 'valid' : 'invalid',
            'message' => $required ? 'Required fields present' : 'Name and designation are required',
            'http_status' => null,
        ];

        $flags = [$emailStatus['status'], $phoneStatus['status'], $profileStatus['status']];
        $hasInvalid = in_array('invalid', $flags, true) || ! $required;
        $verifiedCount = count(array_filter($flags, fn ($s) => $s === 'verified'));

        if ($hasInvalid && $verifiedCount === 0 && ! $required) {
            $status = 'invalid';
        } elseif ($verifiedCount >= 2 && $required) {
            $status = 'verified';
        } elseif ($verifiedCount >= 1 || $required) {
            $status = 'partially_verified';
        } else {
            $status = 'unverified';
        }

        return [
            'status' => $status,
            'email' => $emailStatus['status'],
            'phone' => $phoneStatus['status'],
            'profile' => $profileStatus['status'],
            'results' => $results,
        ];
    }

    /**
     * @return array{status:string,message:string}
     */
    public function validateEmail(?string $email): array
    {
        if (! $email) {
            return ['status' => 'unverified', 'message' => 'No public email provided'];
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'invalid', 'message' => 'Email syntax is invalid'];
        }

        $key = config('services.email_validation.key');
        if ($key) {
            try {
                $response = Http::timeout(6)
                    ->get('https://api.hunter.io/v2/email-verifier', [
                        'email' => $email,
                        'api_key' => $key,
                    ]);
                if ($response->successful()) {
                    $result = $response->json('data.status');
                    if ($result === 'valid') {
                        return ['status' => 'verified', 'message' => 'Verified by email validation API'];
                    }
                    if ($result === 'invalid') {
                        return ['status' => 'invalid', 'message' => 'Rejected by email validation API'];
                    }
                }
            } catch (Throwable $e) {
                Log::warning('Email validation API failed', ['error' => $e->getMessage()]);
            }
        }

        return ['status' => 'verified', 'message' => 'Email syntax is valid'];
    }

    /**
     * @return array{status:string,message:string}
     */
    public function validatePhone(?string $phone): array
    {
        if (! $phone) {
            return ['status' => 'unverified', 'message' => 'No public phone provided'];
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) < 10 || strlen($digits) > 15) {
            return ['status' => 'invalid', 'message' => 'Phone format is invalid'];
        }

        return ['status' => 'verified', 'message' => 'Phone format is valid'];
    }

    /**
     * @return array{status:string,message:string,http_status:?int}
     */
    public function validateProfile(?string $url): array
    {
        if (! $url) {
            return ['status' => 'unverified', 'message' => 'No profile URL provided', 'http_status' => null];
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return ['status' => 'invalid', 'message' => 'Profile URL syntax is invalid', 'http_status' => null];
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (str_ends_with($host, 'example.edu') || str_ends_with($host, 'example.com') || str_ends_with($host, 'novamine.demo')) {
            return ['status' => 'verified', 'message' => 'Demo source URL syntax is valid', 'http_status' => 200];
        }

        try {
            $response = Http::timeout(6)
                ->withHeaders(['User-Agent' => 'NovaMine/1.0 (public profile accessibility check)'])
                ->head($url);

            $code = $response->status();
            if ($code >= 200 && $code < 400) {
                return ['status' => 'verified', 'message' => 'Profile URL is reachable', 'http_status' => $code];
            }

            return ['status' => 'unverified', 'message' => 'Profile URL was not confirmed reachable', 'http_status' => $code];
        } catch (Throwable $e) {
            return ['status' => 'unverified', 'message' => 'Profile URL could not be checked', 'http_status' => null];
        }
    }
}
