<?php

namespace App\Services\Intelligence;

class RuleBasedIntelligenceProvider implements CandidateIntelligenceProviderInterface
{
    public function scoreCandidate(array $candidate, array $criteria): array
    {
        $requiredSkills = $this->skillList($criteria['skills'] ?? []);
        $candidateSkills = $this->skillList($candidate['skills'] ?? []);
        $skillMatch = $this->matchSkills($candidateSkills, $requiredSkills);

        $skillScore = $requiredSkills === []
            ? 80
            : (int) round((count($skillMatch['matching']) / count($requiredSkills)) * 100);

        $experienceScore = $this->experienceScore(
            $candidate['years_experience'] ?? $candidate['experience'] ?? null,
            (string) ($criteria['experience'] ?? '')
        );

        $roleScore = $this->textScore($candidate['designation'] ?? '', $criteria['role'] ?? '');
        $industryScore = $this->textScore($candidate['industry'] ?? '', $criteria['industry'] ?? 'Technology');
        $locationScore = $this->locationScore($candidate['location'] ?? '', $criteria['location'] ?? '');
        $educationScore = ! empty($candidate['education']) ? 80 : 40;
        $completeness = $this->completeness($candidate);

        $score = (int) round(
            $skillScore * 0.40
            + $experienceScore * 0.20
            + $roleScore * 0.15
            + $industryScore * 0.10
            + $locationScore * 0.05
            + $educationScore * 0.05
            + $completeness * 0.05
        );

        $score = max(0, min(100, $score));

        $strengths = [];
        $weaknesses = [];
        if ($skillScore >= 70) {
            $strengths[] = 'Strong skill overlap with the search';
        } else {
            $weaknesses[] = 'Limited overlap with requested skills';
        }
        if ($experienceScore >= 70) {
            $strengths[] = 'Experience aligns with the requested range';
        } else {
            $weaknesses[] = 'Experience is outside the preferred range';
        }
        if ($completeness < 60) {
            $weaknesses[] = 'Profile is missing public contact or source details';
        }

        $analysis = [
            'match_score' => $score,
            'score_breakdown' => [
                'skills' => $skillScore,
                'experience' => $experienceScore,
                'job_role' => $roleScore,
                'industry' => $industryScore,
                'location' => $locationScore,
                'education' => $educationScore,
                'completeness' => $completeness,
            ],
            'matching_skills' => $skillMatch['matching'],
            'missing_skills' => $skillMatch['missing'],
            'strengths' => $strengths,
            'weaknesses' => $weaknesses,
            'explanation' => null,
        ];
        $analysis['explanation'] = $this->generateExplanation($analysis);

        return $analysis;
    }

    public function analyzeCandidate(array $candidate, array $criteria): array
    {
        return $this->scoreCandidate($candidate, $criteria);
    }

    public function matchSkills(array $candidateSkills, array $requiredSkills): array
    {
        $candidate = array_map(fn ($s) => strtolower($s), $candidateSkills);
        $matching = [];
        $missing = [];

        foreach ($requiredSkills as $skill) {
            $found = false;
            foreach ($candidate as $owned) {
                if ($owned === strtolower($skill) || str_contains($owned, strtolower($skill)) || str_contains(strtolower($skill), $owned)) {
                    $found = true;
                    break;
                }
            }
            if ($found) {
                $matching[] = $skill;
            } else {
                $missing[] = $skill;
            }
        }

        return ['matching' => $matching, 'missing' => $missing];
    }

    public function generateExplanation(array $analysis): ?string
    {
        $score = $analysis['match_score'] ?? 0;
        $matching = implode(', ', $analysis['matching_skills'] ?? []) ?: 'none';
        $missing = implode(', ', $analysis['missing_skills'] ?? []) ?: 'none';

        return "Deterministic score {$score}/100. Matching skills: {$matching}. Missing skills: {$missing}.";
    }

    /**
     * @param  mixed  $skills
     * @return list<string>
     */
    private function skillList(mixed $skills): array
    {
        if (is_string($skills)) {
            $skills = preg_split('/[,;]+/', $skills) ?: [];
        }

        return array_values(array_filter(array_map('trim', (array) $skills)));
    }

    private function experienceScore(mixed $years, string $range): int
    {
        if ($years === null || $years === '') {
            return 50;
        }
        $years = (int) $years;
        $min = 0;
        $max = 40;
        if (preg_match('/(\d+)\s*[–-]\s*(\d+)/u', $range, $m)) {
            $min = (int) $m[1];
            $max = (int) $m[2];
        } elseif (preg_match('/(\d+)\s*\+/', $range, $m)) {
            $min = (int) $m[1];
            $max = 40;
        }

        if ($years >= $min && $years <= $max) {
            return 100;
        }
        $distance = $years < $min ? $min - $years : $years - $max;

        return max(20, 100 - ($distance * 15));
    }

    private function textScore(?string $value, ?string $target): int
    {
        $value = strtolower(trim((string) $value));
        $target = strtolower(trim((string) $target));
        if ($target === '') {
            return 70;
        }
        if ($value === '') {
            return 40;
        }
        if ($value === $target || str_contains($value, $target) || str_contains($target, $value)) {
            return 100;
        }
        similar_text($value, $target, $pct);

        return (int) round($pct);
    }

    private function locationScore(?string $value, ?string $target): int
    {
        $value = strtolower((string) $value);
        $target = strtolower((string) $target);
        if ($target === '') {
            return 70;
        }
        $city = trim(explode(',', $target)[0]);
        if ($city !== '' && str_contains($value, $city)) {
            return 100;
        }

        return $this->textScore($value, $target);
    }

    private function completeness(array $candidate): int
    {
        $fields = ['name', 'designation', 'company', 'location', 'professional_email', 'phone', 'profile_url', 'source_name'];
        $present = 0;
        foreach ($fields as $field) {
            $key = $field === 'designation' ? ($candidate['designation'] ?? $candidate['role'] ?? null) : ($candidate[$field] ?? null);
            if ($field === 'designation') {
                $key = $candidate['designation'] ?? $candidate['role'] ?? null;
            }
            if (filled($key)) {
                $present++;
            }
        }
        if (! empty($candidate['skills'])) {
            $present++;
        }
        if (! empty($candidate['education'])) {
            $present++;
        }

        return (int) round(($present / 10) * 100);
    }
}
