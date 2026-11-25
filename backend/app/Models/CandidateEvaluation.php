<?php

namespace App\Models;

use App\Enums\EvaluationRecommendation;
use Database\Factories\CandidateEvaluationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One person's evaluation of a candidate after the interview (REC-INT): three 1 to 5 grades (knowledge of the position,
 * appearance, experience in the position), their average as the overall rating, a recommendation and a note. An evaluation
 * given before the grades were split has only the overall rating, and no grade per category.
 *
 * @property int $id
 * @property int $candidate_id
 * @property int|null $user_id
 * @property int|null $knowledge_rating
 * @property int|null $appearance_rating
 * @property int|null $experience_rating
 * @property float $rating The average of the three grades, to two decimals
 * @property EvaluationRecommendation $recommendation
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['candidate_id', 'user_id', 'knowledge_rating', 'appearance_rating', 'experience_rating', 'rating', 'recommendation', 'note'])]
class CandidateEvaluation extends Model
{
    /** @use HasFactory<CandidateEvaluationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'knowledge_rating' => 'integer',
            'appearance_rating' => 'integer',
            'experience_rating' => 'integer',
            'rating' => 'float',
            'recommendation' => EvaluationRecommendation::class,
        ];
    }

    /**
     * @return BelongsTo<Candidate, $this>
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
