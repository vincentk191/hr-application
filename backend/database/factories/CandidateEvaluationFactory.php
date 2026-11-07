<?php

namespace Database\Factories;

use App\Enums\EvaluationRecommendation;
use App\Models\Candidate;
use App\Models\CandidateEvaluation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CandidateEvaluation>
 */
class CandidateEvaluationFactory extends Factory
{
    /**
     * A positive evaluation.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'candidate_id' => Candidate::factory(),
            'user_id' => User::factory(),
            'rating' => 4,
            'recommendation' => EvaluationRecommendation::Continue,
            'note' => 'Komunikasi baik.',
        ];
    }
}
