<?php

namespace App\Http\Requests;

use App\Models\ProfileChangeRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * HR approving or rejecting a profile change (EMP-CHG-003). A rejection needs a reason, as every
 * rejection in the app does.
 */
class DecideProfileChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ProfileChangeRequest $change */
        $change = $this->route('profileChange');

        return $this->user()?->can('decide', $change) === true;
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
        return [
            'note.required_if' => 'Tulis alasan penolakan.',
        ];
    }

    public function isApproval(): bool
    {
        return $this->input('decision') === 'approve';
    }
}
