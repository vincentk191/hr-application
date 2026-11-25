<?php

namespace App\Actions\Recruitment;

use App\Enums\EvaluationRecommendation;
use App\Models\Candidate;
use App\Models\CandidateEvaluation;
use App\Models\User;

/**
 * An interviewer gives, or changes, their evaluation of a candidate (REC-INT): a grade for each of the three categories,
 * whose average is the overall rating. One per person per candidate: saving again replaces it.
 */
class SaveEvaluation
{
    /**
     * @param  array{knowledge_rating: int, appearance_rating: int, experience_rating: int, recommendation: string, note: string|null}  $fields
     */
    public function handle(Candidate $candidate, User $by, array $fields): CandidateEvaluation
    {
        return CandidateEvaluation::query()->updateOrCreate(
            ['candidate_id' => $candidate->id, 'user_id' => $by->id],
            [
                'knowledge_rating' => $fields['knowledge_rating'],
                'appearance_rating' => $fields['appearance_rating'],
                'experience_rating' => $fields['experience_rating'],
                'rating' => round(($fields['knowledge_rating'] + $fields['appearance_rating'] + $fields['experience_rating']) / 3, 2),
                'recommendation' => EvaluationRecommendation::from($fields['recommendation']),
                'note' => filled($fields['note']) ? trim((string) $fields['note']) : null,
            ],
        );
    }
}
