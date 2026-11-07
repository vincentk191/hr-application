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
 * One person's evaluation of a candidate after the interview (REC-INT): a 1 to 5 rating, a recommendation and a note.
 *
 * @property int $id
 * @property int $candidate_id
 * @property int|null $user_id
 * @property int $rating
 * @property EvaluationRecommendation $recommendation
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['candidate_id', 'user_id', 'rating', 'recommendation', 'note'])]
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
            'rating' => 'integer',
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
