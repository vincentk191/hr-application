<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Employee::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            'dry_run' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Pilih berkas CSV yang akan diimpor.',
            'file.mimes' => 'Berkas harus berformat CSV. Jika dari Excel, simpan sebagai CSV terlebih dahulu.',
            'file.max' => 'Ukuran berkas maksimal 2 MB.',
        ];
    }
}
