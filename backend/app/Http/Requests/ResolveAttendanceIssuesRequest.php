<?php

namespace App\Http\Requests;

use App\Enums\AttendanceIssueType;
use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A batch of issues to excuse or reopen. The reason is the same for the whole batch, and required:
 * "excused" with no explanation would make the audit trail say nothing.
 */
class ResolveAttendanceIssuesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::CorrectAttendance->value) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.employee_id' => ['required', 'integer'],
            'items.*.date' => ['required', 'date_format:Y-m-d'],
            'items.*.type' => ['required', Rule::enum(AttendanceIssueType::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan wajib diisi.',
            'reason.min' => 'Jelaskan alasannya (minimal 5 karakter).',
            'items.required' => 'Pilih minimal satu masalah.',
            'items.max' => 'Maksimal 200 masalah sekaligus.',
        ];
    }
}
