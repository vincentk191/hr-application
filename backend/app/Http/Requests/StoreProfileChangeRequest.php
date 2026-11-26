<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\ProfileChangeRequest;
use App\Support\Employees\EmployeeFormat;
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
     * Numbers, phones and list entries are written in their stored form before they are checked (see EmployeeFormat).
     */
    protected function prepareForValidation(): void
    {
        $this->merge(EmployeeFormat::normalise($this->input()));
    }

    /**
     * Each field is held to the same format as on HR's form. A field that has a value cannot be cleared here: it may be
     * changed, not emptied. One that is empty already may stay empty.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = ['photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']];
        $employee = $this->route('employee');

        foreach (ProfileChangeRequest::FIELDS as $field => $definition) {
            // A field left out of the form is left alone; one that has a value cannot be sent empty.
            $stays = $employee instanceof Employee && filled($employee->getAttribute($field)) ? ['sometimes', 'required'] : ['nullable'];

            $rules[$field] = match ($definition['type']) {
                'photo' => ['prohibited'],
                'email' => [...$stays, 'email', 'max:255'],
                'textarea' => [...$stays, 'string', 'max:1000'],
                default => [...$stays, 'max:255', ...EmployeeFormat::rules($field)],
            };
        }

        return $rules;
    }

    /**
     * The names the form gives the fields, so a message says "Alamat wajib diisi" and not "address".
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_map(fn (array $definition): string => $definition['label'], ProfileChangeRequest::FIELDS);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...EmployeeFormat::messages(),
            'photo.image' => 'Foto harus berupa gambar.',
            'photo.mimes' => 'Foto harus berformat JPG, PNG, atau WebP.',
            'photo.max' => 'Ukuran foto maksimal 2 MB.',
            'email.email' => 'Email tidak valid.',
        ];
    }
}
