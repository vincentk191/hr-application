<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideLeaveRequestRequest extends FormRequest
{
    /**
     * Authorisation is the policy's job, and the policy asks the workflow engine whose step
     * this is — so the route cannot be used to decide someone else's step.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('decide', $this->route('leaveRequest')) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            // Every rejection in the spec carries a note back to the requester
            // (REQ-DLG-005, mirroring offboarding's OFF-APR-006).
            'note' => [
                Rule::requiredIf(fn (): bool => $this->input('decision') === 'reject'),
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.required' => 'Penolakan harus disertai alasan.',
        ];
    }

    public function isApproval(): bool
    {
        return $this->validated()['decision'] === 'approve';
    }

    public function note(): ?string
    {
        return $this->validated()['note'] ?? null;
    }
}
