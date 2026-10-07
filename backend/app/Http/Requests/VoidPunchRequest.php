<?php

namespace App\Http\Requests;

use App\Models\AttendancePunch;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;

class VoidPunchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $punch = $this->route('punch');
        $employee = $punch instanceof AttendancePunch ? $punch->employee : null;

        return $employee instanceof Employee && $this->user()?->can('correctAttendance', $employee) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan pembatalan wajib diisi.',
            'reason.min' => 'Jelaskan alasan pembatalan (minimal 5 karakter).',
        ];
    }
}
