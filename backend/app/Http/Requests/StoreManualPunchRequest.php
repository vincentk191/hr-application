<?php

namespace App\Http\Requests;

use App\Models\AttendancePunch;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Add a punch by hand, or change the time of an existing one. Used for both, because the input is
 * the same: a local date, a local time and the reason it was needed (EMP-ATT-001).
 */
class StoreManualPunchRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Adding a punch is routed by employee; changing one is routed by the punch itself.
        $subject = $this->route('employee') ?? $this->route('punch');
        $employee = $subject instanceof AttendancePunch ? $subject->employee : $subject;

        return $employee instanceof Employee && $this->user()?->can('correctAttendance', $employee) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.required' => 'Pilih tanggal.',
            'time.required' => 'Isi jam.',
            'reason.required' => 'Alasan koreksi wajib diisi.',
            'reason.min' => 'Jelaskan alasan koreksi (minimal 5 karakter).',
        ];
    }
}
