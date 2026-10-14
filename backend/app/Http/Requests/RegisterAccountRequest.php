<?php

namespace App\Http\Requests;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Claiming the account for an employee profile HR already created (CORE-REG-001).
 *
 * Nothing here checks the NIK or ID against the records: that is the action's job, and a miss must
 * look the same whatever part was wrong, so it never appears as a field error.
 */
class RegisterAccountRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'national_id' => ['required', 'string', 'max:50'],
            'employee_number' => ['required', 'string', 'max:50'],
            'password' => $this->passwordRules(),
            // Optional, never verified, and not a sign-in name: the employee ID is.
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'national_id.required' => 'Isi NIK.',
            'employee_number.required' => 'Isi ID karyawan.',
            'email.email' => 'Email tidak valid.',
            'email.unique' => 'Email ini sudah dipakai akun lain.',
        ];
    }
}
