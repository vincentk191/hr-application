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
            'device_sn' => ['required', 'string', 'max:100'],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:'.config('attendance.max_upload_kb')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'device_sn.required' => 'Isi nomor seri (SN) pemindai.',
            'file.required' => 'Pilih berkas log yang akan diunggah.',
            'file.mimes' => 'Berkas harus berformat CSV. Jika dari Excel, simpan sebagai CSV terlebih dahulu.',
            'file.max' => 'Ukuran berkas terlalu besar.',
        ];
    }
}
