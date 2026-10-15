<?php

namespace App\Concerns;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Support\CompanyContext;
use Illuminate\Validation\Rule;

/**
 * The dossier field rules, shared by create and update (EMP-ADD-002).
 *
 * Kept in one place so the two forms cannot drift: the only thing that legitimately differs
 * between them is the uniqueness check on the employee number.
 */
trait ValidatesEmployeeDossier
{
    /**
     * @return array<string, mixed>
     */
    protected function dossierRules(?Employee $ignoring = null): array
    {
        return [
            // Unique within the company, not group-wide: two companies may both have an 001.
            'employee_number' => [
                'required', 'string', 'max:50',
                Rule::unique(Employee::class, 'employee_number')
                    ->where('company_id', $this->activeCompanyId())
                    ->ignore($ignoring),
            ],
            'full_name' => ['required', 'string', 'max:255'],
            // Profile picture: an image only, and small enough for a directory card.
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['nullable', 'boolean'],
            // Department, position and manager are resolved through the company scope, so a
            // record from another company simply does not exist to be selected.
            'department_id' => ['nullable', 'integer', Rule::exists(Department::class, 'id')],
            'position_id' => ['nullable', 'integer', Rule::exists(Position::class, 'id')],
            'manager_id' => ['nullable', 'integer', Rule::exists(Employee::class, 'id')],
            'employment_status' => ['required', Rule::enum(EmploymentStatus::class)],
            'employment_type' => ['nullable', Rule::enum(EmploymentType::class)],
            // The notice period in the contract, in days: what an offboarding case is checked against.
            'notice_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'joined_on' => ['nullable', 'date'],
            'exited_on' => ['nullable', 'date', 'after_or_equal:joined_on'],

            // Contact + emergency contact (EMP-DET-002)
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            // NIK is half of the key an employee registers with (CORE-REG), so it is required, exactly
            // 16 digits, and unique across the group, not just the company. The unique rule reads the
            // table directly, so it ignores the company scope, which is what is wanted here.
            'national_id' => [
                'required', 'string', 'regex:/^\d{16}$/',
                Rule::unique(Employee::class, 'national_id')->ignore($ignoring),
            ],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:100'],

            // Bank and tax (EMP-DET-003)
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:100'],
            'bank_account_holder' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'payment_method' => ['nullable', 'string', 'max:50'],

            // Payroll (EMP-DET-004) — integer rupiah, never a float.
            'pay_group' => ['nullable', 'string', 'max:100'],
            'base_salary_idr' => ['nullable', 'integer', 'min:0'],
            'allowances_idr' => ['nullable', 'integer', 'min:0'],
            'overtime_rate_idr' => ['nullable', 'integer', 'min:0'],

            // Health and insurance (EMP-DET-005)
            'insurance_number' => ['nullable', 'string', 'max:100'],
            'insurance_tier' => ['nullable', 'string', 'max:100'],
            'blood_type' => ['nullable', 'string', 'max:10'],
            'height_cm' => ['nullable', 'integer', 'min:0', 'max:300'],
            'weight_kg' => ['nullable', 'integer', 'min:0', 'max:500'],
            'last_medical_check_on' => ['nullable', 'date'],
            'declared_conditions' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function dossierMessages(): array
    {
        return [
            'employee_number.required' => 'Isi ID karyawan.',
            'employee_number.unique' => 'ID karyawan ini sudah dipakai di perusahaan ini.',
            'full_name.required' => 'Isi nama lengkap.',
            'national_id.required' => 'Isi NIK.',
            'national_id.regex' => 'NIK harus 16 digit angka.',
            'national_id.unique' => 'NIK ini sudah terdaftar pada karyawan lain.',
            'photo.image' => 'Foto harus berupa gambar.',
            'photo.mimes' => 'Foto harus berformat JPG, PNG, atau WebP.',
            'photo.max' => 'Ukuran foto maksimal 2 MB.',
            'exited_on.after_or_equal' => 'Tanggal keluar tidak boleh sebelum tanggal bergabung.',
        ];
    }

    /**
     * The company a new record belongs to: the active one for a pinned user, and the one being
     * viewed for a parent-wide user (EMP-ADD-004).
     */
    protected function activeCompanyId(): ?int
    {
        return app(CompanyContext::class)->companyId()
            ?? $this->user()?->company_id;
    }
}
