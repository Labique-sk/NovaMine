<?php

namespace App\Services\Intelligence;

class CandidateScoringService
{
    public function __construct(
        private readonly CandidateIntelligenceProviderInterface $provider
    ) {
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<string, mixed>  $criteria
     */
    public function score(array $candidate, array $criteria): array
    {
        return $this->provider->scoreCandidate($candidate, $criteria);
    }
}
