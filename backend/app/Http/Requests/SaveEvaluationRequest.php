<?php

namespace App\Http\Requests;

use App\Enums\EvaluationRecommendation;
use App\Models\Candidate;
use App\Models\Requisition;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An evaluation after the interview (REC-INT): a grade from 1 to 5 for each of three categories, a recommendation and an
 * optional note.
 */
class SaveEvaluationRequest extends FormRequest
{
    /** The three categories graded, by field name. */
    public const RATINGS = ['knowledge_rating', 'appearance_rating', 'experience_rating'];

    public function authorize(): bool
    {
        $requisition = $this->route('requisition');
        $candidate = $this->route('candidate');

        return $requisition instanceof Requisition
            && $candidate instanceof Candidate
            && $this->user()?->can('evaluate', [$requisition, $candidate]) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'knowledge_rating' => ['required', 'integer', 'between:1,5'],
            'appearance_rating' => ['required', 'integer', 'between:1,5'],
            'experience_rating' => ['required', 'integer', 'between:1,5'],
            'recommendation' => ['required', Rule::enum(EvaluationRecommendation::class)],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [
            'recommendation.required' => 'Pilih rekomendasi.',
            'recommendation.enum' => 'Rekomendasi tidak dikenal.',
            'note.max' => 'Catatan terlalu panjang (maksimal 1000 huruf).',
        ];

        foreach (self::RATINGS as $field) {
            foreach (['required', 'integer', 'between'] as $rule) {
                $messages["{$field}.{$rule}"] = 'Pilih nilai 1 sampai 5.';
            }
        }

        return $messages;
    }

    /**
     * @return array{knowledge_rating: int, appearance_rating: int, experience_rating: int, recommendation: string, note: string|null}
     */
    public function evaluationFields(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return [
            'knowledge_rating' => (int) $validated['knowledge_rating'],
            'appearance_rating' => (int) $validated['appearance_rating'],
            'experience_rating' => (int) $validated['experience_rating'],
            'recommendation' => (string) $validated['recommendation'],
            'note' => isset($validated['note']) ? (string) $validated['note'] : null,
        ];
    }
}
