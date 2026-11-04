<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * What a candidate sends from their personal link (REC-INT): the details. The yes/no answers are checked against the
 * requisition's checklist by the controller, since the questions depend on whose link it is. Open to anyone holding a
 * link; the link itself is what is checked.
 */
class SubmitCandidateFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'answers' => ['nullable', 'array'],
            'answers.*' => ['in:yes,no'],
            'expected_salary' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'available_from' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.SaveRequisitionRequest::today()->toDateString()],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'answers.*.in' => 'Jawaban harus Ya atau Tidak.',
            'expected_salary.integer' => 'Gaji yang diharapkan harus angka.',
            'expected_salary.min' => 'Gaji yang diharapkan tidak boleh negatif.',
            'expected_salary.max' => 'Gaji yang diharapkan terlalu besar.',
            'available_from.date_format' => 'Tanggal tidak valid.',
            'available_from.after_or_equal' => 'Tanggal mulai tidak boleh di masa lalu.',
            'message.max' => 'Pesan terlalu panjang (maksimal 1000 huruf).',
        ];
    }

    /**
     * @return array{expected_salary: int|null, available_from: string|null, message: string|null}
     */
    public function details(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return [
            'expected_salary' => filled($validated['expected_salary'] ?? null) ? (int) $validated['expected_salary'] : null,
            'available_from' => filled($validated['available_from'] ?? null) ? (string) $validated['available_from'] : null,
            'message' => filled($validated['message'] ?? null) ? trim((string) $validated['message']) : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function answers(): array
    {
        /** @var array<string, string> $answers */
        $answers = $this->validated('answers') ?? [];

        return $answers;
    }
}
