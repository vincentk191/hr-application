<?php

namespace App\Http\Requests;

use App\Models\Requisition;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A decision on the open step of a requisition (REC-APR-002, REC-APR-006). Management may give the salary
 * expectation with its approval (REC-APR-008); whether one is needed is the action's to check.
 */
class DecideRequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $requisition = $this->route('requisition');

        return $requisition instanceof Requisition && $this->user()?->can('decide', $requisition) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:approve,reject'],
            'note' => ['nullable', 'string', 'max:2000', 'required_if:decision,reject'],
            ...self::salaryRules(),
        ];
    }

    /**
     * The salary range, also used on its own by "Simpan" (REC-APR-007).
     *
     * @return array<string, list<string>>
     */
    public static function salaryRules(): array
    {
        return [
            'salary_min' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'salary_max' => ['nullable', 'integer', 'min:0', 'max:1000000000', 'gte:salary_min'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.required_if' => 'Tulis alasan penolakan untuk pemohon.',
            ...self::salaryMessages(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function salaryMessages(): array
    {
        return [
            'salary_min.integer' => 'Gaji minimal harus angka.',
            'salary_max.integer' => 'Gaji maksimal harus angka.',
            'salary_max.gte' => 'Gaji maksimal tidak boleh di bawah gaji minimal.',
        ];
    }

    public function isApproval(): bool
    {
        return $this->input('decision') === 'approve';
    }
}
