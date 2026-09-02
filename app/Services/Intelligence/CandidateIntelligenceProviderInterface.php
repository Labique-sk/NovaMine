<?php

namespace App\Services\Intelligence;

interface CandidateIntelligenceProviderInterface
{
    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<string, mixed>  $criteria
     * @return array{
     *     match_score:int,
     *     score_breakdown:array<string,int|float>,
     *     matching_skills:list<string>,
     *     missing_skills:list<string>,
     *     strengths:list<string>,
     *     weaknesses:list<string>,
     *     explanation:?string
     * }
     */
    public function scoreCandidate(array $candidate, array $criteria): array;

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<string, mixed>  $criteria
     */
    public function analyzeCandidate(array $candidate, array $criteria): array;

    /**
     * @param  list<string>  $candidateSkills
     * @param  list<string>  $requiredSkills
     * @return array{matching:list<string>,missing:list<string>}
     */
    public function matchSkills(array $candidateSkills, array $requiredSkills): array;

    public function generateExplanation(array $analysis): ?string;
}
