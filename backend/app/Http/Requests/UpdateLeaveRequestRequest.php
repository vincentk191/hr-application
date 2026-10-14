<?php

namespace App\Http\Requests;

use App\Concerns\ValidatesLeaveRequestFields;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Correcting a rejected leave request and sending it for approval again. Who may is the policy's
 * call (the requester, or HR for someone without a login), and only while the request is rejected.
 */
class UpdateLeaveRequestRequest extends FormRequest
{
    use ValidatesLeaveRequestFields;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('leaveRequest')) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->leaveFieldRules($this->currentLeave()->leave_type),
            // What changed, for the approvers: kept in the request's history with the new submission.
            'note' => ['nullable', 'string', 'max:2000'],
            'remove_attachments' => ['nullable', 'array'],
            'remove_attachments.*' => ['integer'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var LeaveRequest $leave */
                $leave = $this->route('leaveRequest');

                $this->checkLeaveLimit($validator);
                $this->checkBackdating($validator, $leave->start_on);

                $removing = count(array_filter((array) $this->input('remove_attachments', []), fn ($id): bool => $leave->attachments->contains('id', (int) $id)));
                $adding = count((array) $this->file('attachments', []));

                if ($leave->attachments->count() - $removing + $adding > self::MAX_ATTACHMENTS) {
                    $validator->errors()->add('attachments', 'Lampiran maksimal '.self::MAX_ATTACHMENTS.' berkas.');
                }
            },
        ];
    }

    private function currentLeave(): LeaveRequest
    {
        /** @var LeaveRequest $leave */
        $leave = $this->route('leaveRequest');

        return $leave;
    }

    protected function leaveEmployee(): ?Employee
    {
        return $this->currentLeave()->employee;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->leaveFieldMessages();
    }
}
