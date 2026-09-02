<?php

namespace App\Services\Mining;

class CandidateNormalizer
{
    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    public function normalize(array $record): array
    {
        $name = trim((string) ($record['name'] ?? ''));
        $email = $this->normalizeEmail($record['email'] ?? null);
        $phone = $this->normalizePhone($record['phone'] ?? null);

        $skills = $record['skills'] ?? [];
        if (is_string($skills)) {
            $skills = preg_split('/[,;]+/', $skills) ?: [];
        }

        $education = $record['education'] ?? [];
        if (is_string($education) && $education !== '') {
            $education = [['credential' => $education]];
        }

        return [
            'name' => $name,
            'designation' => $this->nullIfEmpty($record['designation'] ?? $record['role'] ?? null),
            'company' => $this->nullIfEmpty($record['company'] ?? null),
            'years_experience' => $this->experience($record['experience'] ?? $record['years_experience'] ?? null),
            'industry' => $this->nullIfEmpty($record['industry'] ?? null),
            'location' => $this->nullIfEmpty($record['location'] ?? null),
            'professional_email' => $email,
            'phone' => $phone,
            'profile_url' => $this->normalizeUrl($record['profile_url'] ?? null),
            'portfolio_url' => $this->normalizeUrl($record['portfolio_url'] ?? null),
            'source_url' => $this->normalizeUrl($record['source_url'] ?? null),
            'source_name' => $this->nullIfEmpty($record['source_name'] ?? null),
            'candidate_type' => $this->nullIfEmpty($record['candidate_type'] ?? null),
            'is_synthetic' => (bool) ($record['is_synthetic'] ?? false),
            'skills' => array_values(array_unique(array_filter(array_map(
                fn ($skill) => trim((string) $skill),
                (array) $skills
            )))),
            'education' => array_values(array_filter((array) $education)),
        ];
    }

    public function normalizeEmail(?string $email): ?string
    {
        $email = strtolower(trim((string) $email));

        return $email !== '' ? $email : null;
    }

    public function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === null || strlen($digits) < 8) {
            return $phone ? trim($phone) : null;
        }

        if (str_starts_with($digits, '91') && strlen($digits) === 12) {
            return '+91 '.substr($digits, 2);
        }

        return '+'.$digits;
    }

    public function normalizeUrl(mixed $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (! preg_match('/^https?:\/\//i', $url)) {
            $url = 'https://'.$url;
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    private function experience(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return max(0, (int) $value);
        }
        if (preg_match('/(\d+)/', (string) $value, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
