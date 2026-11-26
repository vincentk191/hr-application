<?php

namespace App\Concerns;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\Permission;
use App\Enums\PtkpStatus;
use App\Enums\Religion;
use App\Enums\Zona;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Support\CompanyContext;
use App\Support\Employees\EmployeeFormat;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The dossier field rules, shared by create and update (EMP-ADD-002).
 *
 * Kept in one place so the two forms cannot drift. Nearly every field is required: a new profile must have all of them, and
 * an edit may not blank or break any field it submits (the form submits every field; a request that leaves a field out
 * keeps what is stored). Blok KTP and the end date are the only optional ones. The sensitive fields (bank, tax, BPJS,
 * family card, payroll) are required only of someone who may write them: for anyone else they are not read at all.
 *
 * The formats (digits, phone, RT/RW, lists) are EmployeeFormat's, shared with the change request and the import.
 */
trait ValidatesEmployeeDossier
{
    /**
     * @return array<string, mixed>
     */
    protected function dossierRules(?Employee $ignoring = null): array
    {
        $companyId = $this->dossierCompanyId($ignoring);
        $isNew = $ignoring === null;
        // A new profile has every field; an edit holds the ones it submits to the same standard.
        $must = $isNew ? ['required'] : ['sometimes', 'required'];
        $mayWriteSensitive = $this->user()?->can(Permission::ViewSensitiveData->value) === true;
        $mustIfSensitive = $mayWriteSensitive ? $must : ['nullable'];
        $format = fn (string $field): array => EmployeeFormat::rules($field);
        $inCompany = fn ($rule) => $rule->when($companyId !== null, fn ($rule) => $rule->where('company_id', $companyId));

        return [
            ...$this->companyRules($ignoring),
            // Unique within the company, not group-wide: two companies may both have an 001.
            'employee_number' => [
                'required', 'max:50', ...$format('employee_number'),
                Rule::unique(Employee::class, 'employee_number')
                    ->where('company_id', $companyId)
                    ->ignore($ignoring),
            ],
            'full_name' => ['required', 'max:255', ...$format('full_name')],
            // Profile picture: an image only, small enough for a directory card, and given when the profile is made.
            'photo' => [$isNew ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['nullable', 'boolean'],
            // Cabang, department, position and manager must belong to the record's own company, so a record
            // from another company cannot be selected. Every employee has a Cabang, so a new one must be
            // given; an existing record keeps its own when the field is left out.
            'branch_id' => [$isNew ? 'required' : 'sometimes', 'integer', $inCompany(Rule::exists(Branch::class, 'id'))],
            'department_id' => [...$must, 'integer', $inCompany(Rule::exists(Department::class, 'id'))],
            'position_id' => [...$must, 'integer', $inCompany(Rule::exists(Position::class, 'id'))],
            'job_title' => [...$must, 'string', 'max:150'],
            // A manager already set stays valid when the record is saved: a supervisor's team may span companies, and
            // saving a report must not force its manager out because the manager works in another one. Only one person
            // per company may be without a manager ("Tanpa atasan"), which is the top of the reporting line.
            'manager_id' => [
                ...($isNew ? [] : ['sometimes']),
                Rule::requiredIf(fn (): bool => ! $this->boolean('no_manager')),
                'nullable', 'integer', Rule::exists(Employee::class, 'id')
                    ->when($companyId !== null, fn ($rule) => $rule->where(
                        fn ($query) => $query->where(fn ($same) => $same
                            ->where('company_id', $companyId)
                            ->when($ignoring?->manager_id, fn ($same, $current) => $same->orWhere('id', $current))),
                    )),
                // Only someone still employed whose account is not switched off can be named; the manager already set is left alone.
                function (string $attribute, mixed $value, Closure $fail) use ($ignoring): void {
                    if ((int) $value === $ignoring?->manager_id) {
                        return;
                    }

                    if (! Employee::acrossCompanies()->assignable()->whereKey($value)->exists()) {
                        $fail('Atasan langsung harus karyawan aktif dengan akun yang tidak dinonaktifkan.');
                    }
                },
            ],
            // A profile is only closed by an offboarding case, and only brought back by reactivating it.
            'employment_status' => ['required', Rule::enum(EmploymentStatus::class)->only(EmploymentStatus::Active)],
            'employment_type' => [...$must, Rule::enum(EmploymentType::class)],
            // The notice period in the contract, in days: what an offboarding case is checked against.
            'notice_days' => [...$must, 'integer', 'min:0', 'max:365'],
            // Optional: left out, a new employee is Hijau and an existing one keeps their Zona.
            'zona' => ['sometimes', Rule::enum(Zona::class)],
            'joined_on' => [...$must, 'date', 'before_or_equal:'.now()->addYear()->toDateString()],
            'exited_on' => ['nullable', 'date', 'after_or_equal:joined_on'],

            // Contact + emergency contact (EMP-DET-002)
            'email' => [...$must, 'email', 'max:255'],
            'phone' => [...$must, ...$format('phone')],
            'address' => [...$must, 'string', 'max:1000'],
            // NIK is half of the key an employee registers with (CORE-REG), so it is required, exactly
            // 16 digits, and unique across the group, not just the company. The unique rule reads the
            // table directly, so it ignores the company scope, which is what is wanted here.
            'national_id' => [
                'required', ...$format('national_id'),
                Rule::unique(Employee::class, 'national_id')->ignore($ignoring),
            ],
            'emergency_contact_name' => [...$must, 'max:255', ...$format('emergency_contact_name')],
            'emergency_contact_phone' => [...$must, ...$format('emergency_contact_phone'), ...($this->filled('phone') ? ['different:phone'] : [])],
            'emergency_contact_relation' => [...$must, ...$format('emergency_contact_relation')],

            // Bank and tax (EMP-DET-003)
            'bank_name' => [...$mustIfSensitive, ...$format('bank_name')],
            'bank_account_number' => [...$mustIfSensitive, ...$format('bank_account_number')],
            'bank_account_holder' => [...$mustIfSensitive, 'max:255', ...$format('bank_account_holder')],
            'tax_number' => [...$mustIfSensitive, ...$format('tax_number')],
            'payment_method' => [...$mustIfSensitive, ...$format('payment_method')],

            // Payroll (EMP-DET-004) — integer rupiah, never a float.
            'pay_group' => [...$mustIfSensitive, 'string', 'max:100'],
            'base_salary_idr' => [...$mustIfSensitive, 'integer', 'min:1'],
            'allowances_idr' => [...$mustIfSensitive, 'integer', 'min:0'],
            'overtime_rate_idr' => [...$mustIfSensitive, 'integer', 'min:0'],

            // Personal, from the data sheet
            'gender' => [...$must, Rule::enum(Gender::class)],
            'birth_place' => [...$must, 'max:255', ...$format('birth_place')],
            'birth_date' => [...$must, 'date', 'before_or_equal:'.now()->subYears(17)->toDateString(), 'after_or_equal:'.now()->subYears(70)->toDateString()],
            'religion' => [...$must, Rule::enum(Religion::class)],
            'marital_status' => [...$must, Rule::enum(MaritalStatus::class)],
            'education_level' => [...$must, ...$format('education_level')],

            // The home address (`address` above is its street) and the address on the KTP
            'residence_village' => [...$must, 'string', 'max:255'],
            'residence_district' => [...$must, 'string', 'max:255'],
            'residence_city' => [...$must, 'string', 'max:255'],
            'residence_province' => [...$must, 'string', 'max:255'],
            'residence_postal_code' => [...$must, ...$format('residence_postal_code')],
            'id_address' => [...$must, 'string', 'max:1000'],
            'id_block' => ['nullable', 'string', 'max:50'],
            'id_house_number' => [...$must, 'string', 'max:50'],
            'id_rt' => [...$must, ...$format('id_rt')],
            'id_rw' => [...$must, ...$format('id_rw')],
            'id_village' => [...$must, 'string', 'max:255'],
            'id_district' => [...$must, 'string', 'max:255'],
            'id_city' => [...$must, 'string', 'max:255'],
            'id_province' => [...$must, 'string', 'max:255'],
            'id_postal_code' => [...$must, ...$format('id_postal_code')],

            // Sensitive, from the data sheet. Shared by a household, so a family card number is not unique.
            'family_card_number' => [...$mustIfSensitive, ...$format('family_card_number')],
            'mother_name' => [...$mustIfSensitive, 'max:255', ...$format('mother_name')],
            'ptkp_status' => [...$mustIfSensitive, Rule::enum(PtkpStatus::class)],
            'bank_branch' => [...$mustIfSensitive, 'string', 'max:255'],
            // Kesehatan & asuransi: the two BPJS numbers are all that is kept; the rest is on the BPJS site.
            'bpjs_employment_number' => [...$mustIfSensitive, ...$format('bpjs_employment_number')],
            'bpjs_health_number' => [...$mustIfSensitive, ...$format('bpjs_health_number')],
        ];
    }

    /**
     * Writes the values in their stored form before they are checked: numbers as digits, phones as 08…, RT and RW to three
     * digits, list entries as listed, emails in lower case. "Tanpa atasan" clears the manager. Called by the requests that use these rules.
     */
    protected function normaliseDossier(): void
    {
        $values = EmployeeFormat::normalise($this->input());

        if ($this->boolean('no_manager')) {
            $values['manager_id'] = null;
        }

        $this->merge($values);
    }

    /**
     * @return array<string, string>
     */
    protected function dossierMessages(): array
    {
        return [
            ...EmployeeFormat::messages(),
            'zona.enum' => 'Pilih Zona Hijau, Merah, atau Hitam.',
            'employment_status.enum' => 'Profil hanya ditutup lewat offboarding dan dibuka kembali lewat "Aktifkan kembali".',
            'company_id.required' => 'Pilih perusahaan.',
            'company_id.exists' => 'Perusahaan ini tidak ditemukan.',
            'employee_number.required' => 'Isi ID karyawan.',
            'employee_number.unique' => 'ID karyawan ini sudah dipakai di perusahaan ini.',
            'full_name.required' => 'Isi nama lengkap.',
            'branch_id.required' => 'Pilih cabang.',
            'branch_id.exists' => 'Cabang ini tidak ditemukan di perusahaan ini.',
            'manager_id.required' => 'Pilih atasan langsung, atau centang "Tanpa atasan".',
            'national_id.required' => 'Isi NIK.',
            'national_id.unique' => 'NIK ini sudah terdaftar pada karyawan lain.',
            'photo.required' => 'Foto profil wajib diisi.',
            'photo.image' => 'Foto harus berupa gambar.',
            'photo.mimes' => 'Foto harus berformat JPG, PNG, atau WebP.',
            'photo.max' => 'Ukuran foto maksimal 2 MB.',
            'joined_on.before_or_equal' => 'Tanggal bergabung tidak boleh lebih dari 1 tahun ke depan.',
            'exited_on.after_or_equal' => 'Tanggal keluar tidak boleh sebelum tanggal bergabung.',
            'birth_date.before_or_equal' => 'Usia karyawan harus antara 17 dan 70 tahun.',
            'birth_date.after_or_equal' => 'Usia karyawan harus antara 17 dan 70 tahun.',
            'gender.enum' => 'Pilih Laki-laki atau Perempuan.',
            'religion.enum' => 'Pilih agama dari daftar.',
            'marital_status.enum' => 'Pilih status perkawinan dari daftar.',
            'ptkp_status.enum' => 'Pilih status PTKP dari daftar (mis. TK/0, K/1, K/I/2).',
            'emergency_contact_phone.different' => 'Telepon darurat tidak boleh sama dengan telepon karyawan.',
            'base_salary_idr.min' => 'Gaji pokok harus lebih dari 0.',
        ];
    }

    /**
     * Names for the fields in the messages, so "RT wajib diisi" rather than "id rt wajib diisi".
     *
     * @return array<string, string>
     */
    protected function dossierAttributes(): array
    {
        return [
            'employee_number' => 'ID karyawan', 'full_name' => 'Nama lengkap', 'photo' => 'Foto profil', 'branch_id' => 'Cabang',
            'department_id' => 'Departemen', 'position_id' => 'Posisi', 'job_title' => 'Jabatan', 'manager_id' => 'Atasan langsung',
            'employment_type' => 'Jenis kontrak', 'notice_days' => 'Masa pemberitahuan', 'joined_on' => 'Tanggal bergabung',
            'email' => 'Email', 'phone' => 'Telepon', 'address' => 'Alamat', 'national_id' => 'NIK',
            'emergency_contact_name' => 'Nama kontak darurat', 'emergency_contact_phone' => 'Telepon darurat',
            'emergency_contact_relation' => 'Hubungan', 'bank_name' => 'Bank', 'bank_account_number' => 'No. rekening',
            'bank_account_holder' => 'Atas nama', 'tax_number' => 'NPWP', 'payment_method' => 'Metode pembayaran',
            'pay_group' => 'Kelompok gaji', 'base_salary_idr' => 'Gaji pokok', 'allowances_idr' => 'Tunjangan',
            'overtime_rate_idr' => 'Tarif lembur', 'gender' => 'Jenis kelamin', 'birth_place' => 'Tempat lahir',
            'birth_date' => 'Tanggal lahir', 'religion' => 'Agama', 'marital_status' => 'Status perkawinan',
            'education_level' => 'Pendidikan terakhir', 'residence_village' => 'Kelurahan', 'residence_district' => 'Kecamatan',
            'residence_city' => 'Kabupaten / kota', 'residence_province' => 'Provinsi', 'residence_postal_code' => 'Kode pos',
            'id_address' => 'Alamat KTP', 'id_house_number' => 'No. rumah', 'id_rt' => 'RT', 'id_rw' => 'RW',
            'id_village' => 'Kelurahan KTP', 'id_district' => 'Kecamatan KTP', 'id_city' => 'Kabupaten / kota KTP',
            'id_province' => 'Provinsi KTP', 'id_postal_code' => 'Kode pos KTP', 'family_card_number' => 'No. KK',
            'mother_name' => 'Nama ibu kandung', 'ptkp_status' => 'Status PTKP', 'bank_branch' => 'Cabang bank',
            'bpjs_employment_number' => 'No. BPJS Ketenagakerjaan', 'bpjs_health_number' => 'No. BPJS Kesehatan',
        ];
    }

    /**
     * The checks that need the whole request: only one person in a company may be without a manager, and the PTKP status has to
     * fit the marital status (K for someone married, TK for anyone else).
     *
     * @return list<callable(Validator): void>
     */
    protected function dossierChecks(?Employee $ignoring = null): array
    {
        return [
            function (Validator $validator) use ($ignoring): void {
                $companyId = $this->dossierCompanyId($ignoring);

                if (! $this->boolean('no_manager') || $companyId === null) {
                    return;
                }

                $top = Employee::acrossCompanies()
                    ->where('company_id', $companyId)
                    ->where('employment_status', EmploymentStatus::Active->value)
                    ->whereNull('manager_id')
                    ->when($ignoring !== null, fn ($query) => $query->whereKeyNot($ignoring->id))
                    ->first(['full_name']);

                if ($top !== null) {
                    $validator->errors()->add('manager_id', "Hanya satu karyawan yang boleh tanpa atasan; {$top->full_name} sudah tanpa atasan.");
                }
            },
            function (Validator $validator): void {
                $married = $this->input('marital_status') === MaritalStatus::Married->value;
                $ptkp = (string) $this->input('ptkp_status');

                if ($this->input('marital_status') === null || $ptkp === '' || $validator->errors()->has('ptkp_status')) {
                    return;
                }

                if ($married !== str_starts_with($ptkp, 'K')) {
                    $validator->errors()->add('ptkp_status', $married
                        ? 'Karyawan berstatus kawin: pilih status PTKP K (K/0, K/1, …).'
                        : 'Karyawan belum kawin atau cerai: pilih status PTKP TK (TK/0, TK/1, …).');
                }
            },
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

    /**
     * Someone viewing every company at once has no active company to file a new record under, so the form
     * asks for one. With a company active, or on an existing record, the field is not asked for or read.
     *
     * @return array<string, mixed>
     */
    private function companyRules(?Employee $ignoring): array
    {
        if ($ignoring !== null || $this->activeCompanyId() !== null) {
            return [];
        }

        return ['company_id' => ['required', 'integer', Rule::exists(Company::class, 'id')]];
    }

    /**
     * The company the record's checks are made against: its own when it exists, else the active one, else the
     * one chosen in the form.
     */
    private function dossierCompanyId(?Employee $ignoring): ?int
    {
        return $ignoring?->company_id
            ?? $this->activeCompanyId()
            ?? ($this->filled('company_id') ? $this->integer('company_id') : null);
    }
}
