<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceEnrolMapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-attendance-device') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'device_sn' => ['required', 'string', 'max:100'],
            'pin' => ['required', 'string', 'max:50'],
            // Looked up across companies: the mapping is made by a parent-wide administrator for
            // a device the whole group shares, regardless of which company is on screen.
            'employee_id' => ['required', 'integer', Rule::exists((new Employee)->getTable(), 'id')],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'device_sn.required' => 'Isi nomor seri (SN) pemindai.',
            'pin.required' => 'Isi PIN / ID pemindai.',
            'employee_id.required' => 'Pilih karyawan.',
            'employee_id.exists' => 'Karyawan tidak ditemukan.',
            'valid_to.after_or_equal' => 'Tanggal berakhir tidak boleh sebelum tanggal mulai.',
        ];
    }
}
