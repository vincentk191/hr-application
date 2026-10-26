<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceImportRequest extends FormRequest
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
            'device_sn' => ['nullable', 'string', 'max:100'],
            'file' => ['required', 'file', 'extensions:csv,txt,xls,xlsx', 'max:'.config('attendance.max_upload_kb')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Pilih berkas log yang akan diunggah.',
            'file.extensions' => 'Berkas harus berformat Excel (.xls, .xlsx) atau CSV.',
            'file.max' => 'Ukuran berkas terlalu besar.',
        ];
    }
}
