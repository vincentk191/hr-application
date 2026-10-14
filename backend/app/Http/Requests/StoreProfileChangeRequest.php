<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\ProfileChangeRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An employee's proposed change to their own profile (EMP-CHG-001). Authorisation is the policy's:
 * only the person the record belongs to. The rules are built from the list of self-service fields,
 * so adding a field there is the only change a new field needs.
 */
class StoreProfileChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Employee $employee */
        $employee = $this->route('employee');

        return $this->user()?->can('createFor', [ProfileChangeRequest::class, $employee]) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = ['photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']];

        foreach (ProfileChangeRequest::FIELDS as $field => $definition) {
            $rules[$field] = match ($definition['type']) {
                'photo' => ['prohibited'],
                'email' => ['nullable', 'email', 'max:255'],
                'textarea' => ['nullable', 'string', 'max:1000'],
                default => ['nullable', 'string', 'max:255'],
            };
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.image' => 'Foto harus berupa gambar.',
            'photo.mimes' => 'Foto harus berformat JPG, PNG, atau WebP.',
            'photo.max' => 'Ukuran foto maksimal 2 MB.',
            'email.email' => 'Email tidak valid.',
        ];
    }
}
