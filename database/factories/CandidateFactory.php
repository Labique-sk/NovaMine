<?php

namespace Database\Factories;

use App\Models\Candidate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CandidateFactory extends Factory
{
    protected $model = Candidate::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'mining_job_id' => null,
            'name' => $this->faker->name(),
            'designation' => $this->faker->jobTitle(),
            'company' => $this->faker->company(),
            'years_experience' => $this->faker->numberBetween(1, 15),
            'industry' => $this->faker->word(),
            'location' => $this->faker->city(),
            'professional_email' => $this->faker->email(),
            'phone' => '+1' . $this->faker->numerify('##########'),
            'profile_url' => $this->faker->url(),
            'portfolio_url' => null,
            'source_url' => null,
            'source_name' => 'Test Source',
            'candidate_type' => null,
            'verification_status' => 'unverified',
            'email_validation_status' => 'unverified',
            'phone_validation_status' => 'unverified',
            'profile_validation_status' => 'unverified',
            'duplicate_status' => 'unique',
            'match_score' => $this->faker->numberBetween(60, 95),
            'score_breakdown' => null,
            'is_synthetic' => false,
        ];
    }
}
