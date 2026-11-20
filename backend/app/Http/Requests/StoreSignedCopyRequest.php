<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSignedCopyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('fileSignedCopy', $this->route('document')) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // A scan or a photo of the signed pages.
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Pilih berkas yang akan diunggah.',
            'file.mimes' => 'Berkas harus berformat PDF atau gambar.',
            'file.max' => 'Ukuran berkas maksimal 10 MB.',
        ];
    }
}
