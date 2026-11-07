<?php

namespace App\Actions\Recruitment;

use App\Enums\EvaluationRecommendation;
use App\Models\Candidate;
use App\Models\CandidateEvaluation;
use App\Models\User;

/**
 * An interviewer gives, or changes, their evaluation of a candidate (REC-INT). One per person per candidate: saving
 * again replaces it.
 */
class SaveEvaluation
{
    /**
     * @param  array{rating: int, recommendation: string, note: string|null}  $fields
     */
    public function handle(Candidate $candidate, User $by, array $fields): CandidateEvaluation
    {
        return CandidateEvaluation::query()->updateOrCreate(
            ['candidate_id' => $candidate->id, 'user_id' => $by->id],
            [
                'rating' => $fields['rating'],
                'recommendation' => EvaluationRecommendation::from($fields['recommendation']),
                'note' => filled($fields['note']) ? trim((string) $fields['note']) : null,
            ],
        );
    }
}
