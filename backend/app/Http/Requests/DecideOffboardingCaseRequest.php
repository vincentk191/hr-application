<?php

namespace App\Http\Requests;

use App\Models\OffboardingCase;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Management approving or rejecting a case (OFF-APR-003/006). A rejection needs a reason.
 */
class DecideOffboardingCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var OffboardingCase $case */
        $case = $this->route('offboardingCase');

        return $this->user()?->can('decide', $case) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:approve,reject'],
            'note' => ['nullable', 'string', 'max:2000', 'required_if:decision,reject'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['note.required_if' => 'Tulis catatan untuk pemohon.'];
    }

    public function isApproval(): bool
    {
        return $this->input('decision') === 'approve';
    }
}
