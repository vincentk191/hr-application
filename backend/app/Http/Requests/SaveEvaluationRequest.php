<?php

namespace App\Http\Requests;

use App\Enums\EvaluationRecommendation;
use App\Models\Candidate;
use App\Models\Requisition;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An evaluation after the interview (REC-INT): a rating from 1 to 5, a recommendation and an optional note.
 */
class SaveEvaluationRequest extends FormRequest
{
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
            'rating' => ['required', 'integer', 'between:1,5'],
            'recommendation' => ['required', Rule::enum(EvaluationRecommendation::class)],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating.required' => 'Pilih nilai 1 sampai 5.',
            'rating.integer' => 'Pilih nilai 1 sampai 5.',
            'rating.between' => 'Pilih nilai 1 sampai 5.',
            'recommendation.required' => 'Pilih rekomendasi.',
            'recommendation.enum' => 'Rekomendasi tidak dikenal.',
            'note.max' => 'Catatan terlalu panjang (maksimal 1000 huruf).',
        ];
    }

    /**
     * @return array{rating: int, recommendation: string, note: string|null}
     */
    public function evaluationFields(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return [
            'rating' => (int) $validated['rating'],
            'recommendation' => (string) $validated['recommendation'],
            'note' => isset($validated['note']) ? (string) $validated['note'] : null,
        ];
    }
}
