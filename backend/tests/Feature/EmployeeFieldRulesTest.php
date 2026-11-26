<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;

/**
 * The employee profile form's rules (EMP-ADD): a new profile has every field, an edit may not blank or break what it submits,
 * and numbers, phones, RT/RW and list entries are checked in one format and stored as digits, 08…, three digits and the listed
 * spelling. The same formats apply to the self-service change request and the import (see their own tests).
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->branch = Branch::factory()->forCompany($this->company)->create();
    $this->hr = User::factory()->create(['company_id' => $this->company->id]);
    $this->hr->assignRole(Role::HrAdmin->value);
});

/**
 * @param  array<string, mixed>  $fields
 */
function profileForm(array $fields = []): array
{
    return fullEmployeeForm([
        'employee_number' => 'EMP-5001',
        'national_id' => '3174000000005001',
        'full_name' => 'Budi Santoso',
        'branch_id' => test()->branch->id,
        'employment_status' => 'active',
        ...$fields,
    ]);
}

function saveProfile(array $fields = [], ?User $as = null)
{
    return test()->actingAs($as ?? test()->hr)->from(route('employees.create'))->post(route('employees.store'), profileForm($fields));
}

it('creates a profile from a complete form', function () {
    saveProfile()->assertSessionDoesntHaveErrors()->assertRedirect();

    expect(Employee::firstWhere('employee_number', 'EMP-5001'))->not->toBeNull();
});

it('writes numbers, phones, RT and RW, and list entries the way they are stored', function () {
    saveProfile([
        'national_id' => '3174-0000-0000-5001',
        'family_card_number' => '3174 0000 0000 0009',
        'tax_number' => '12.345.678.9-012.345',
        'bpjs_health_number' => '0001 2345 67890',
        'bpjs_employment_number' => '123-4567-8901',
        'bank_account_number' => '1234.567.890',
        'phone' => '+62 812-3456-7890',
        'emergency_contact_phone' => '62 813 9999 0000',
        'email' => 'Budi.Santoso@Example.Test',
        'id_rt' => '1',
        'id_rw' => '12',
        'id_postal_code' => '10110',
        'bank_name' => 'Bank BCA',
        'education_level' => 'sma',
    ])->assertSessionDoesntHaveErrors();

    $employee = Employee::firstWhere('employee_number', 'EMP-5001');

    expect($employee->national_id)->toBe('3174000000005001')
        ->and($employee->family_card_number)->toBe('3174000000000009')
        ->and($employee->tax_number)->toBe('123456789012345')
        ->and($employee->bpjs_health_number)->toBe('0001234567890')
        ->and($employee->bpjs_employment_number)->toBe('12345678901')
        ->and($employee->bank_account_number)->toBe('1234567890')
        ->and($employee->phone)->toBe('081234567890')
        ->and($employee->emergency_contact_phone)->toBe('081399990000')
        ->and($employee->email)->toBe('budi.santoso@example.test')
        ->and($employee->id_rt)->toBe('001')
        ->and($employee->id_rw)->toBe('012')
        ->and($employee->bank_name)->toBe('BCA')
        ->and($employee->education_level)->toBe('SMA/SMK');
});

it('requires every field of a new profile', function (string $field) {
    saveProfile([$field => null])->assertSessionHasErrors($field);

    expect(Employee::firstWhere('employee_number', 'EMP-5001'))->toBeNull();
})->with([
    'photo', 'department_id', 'position_id', 'job_title', 'manager_id', 'employment_type', 'notice_days', 'joined_on',
    'gender', 'birth_place', 'birth_date', 'religion', 'marital_status', 'education_level',
    'email', 'phone', 'address', 'national_id', 'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation',
    'residence_village', 'residence_district', 'residence_city', 'residence_province', 'residence_postal_code',
    'id_address', 'id_house_number', 'id_rt', 'id_rw', 'id_village', 'id_district', 'id_city', 'id_province', 'id_postal_code',
    'family_card_number', 'mother_name', 'ptkp_status', 'bank_name', 'bank_account_number', 'bank_account_holder', 'bank_branch',
    'tax_number', 'payment_method', 'bpjs_employment_number', 'bpjs_health_number', 'pay_group', 'base_salary_idr',
    'allowances_idr', 'overtime_rate_idr',
]);

it('leaves the KTP block and the exit date optional', function () {
    saveProfile(['id_block' => null, 'exited_on' => null])->assertSessionDoesntHaveErrors();

    expect(Employee::firstWhere('employee_number', 'EMP-5001'))->not->toBeNull();
});

it('holds each field to its format', function (string $field, mixed $value, string $message) {
    saveProfile([$field => $value])->assertSessionHasErrors([$field => $message]);
})->with([
    'RT of four digits' => ['id_rt', '1234', 'RT harus 3 digit angka, mis. 001.'],
    'RW with letters' => ['id_rw', 'abc', 'RW harus 3 digit angka, mis. 001.'],
    'postal code of four digits' => ['residence_postal_code', '1234', 'Kode pos harus 5 digit angka.'],
    'KTP postal code of six digits' => ['id_postal_code', '123456', 'Kode pos KTP harus 5 digit angka.'],
    'family card of 15 digits' => ['family_card_number', '317400000000000', 'No. KK harus 16 digit angka.'],
    'NIK of 15 digits' => ['national_id', '317400000000500', 'NIK harus 16 digit angka.'],
    'NPWP of 14 digits' => ['tax_number', '12345678901234', 'NPWP harus 15 atau 16 digit angka.'],
    'BPJS Kesehatan of 12 digits' => ['bpjs_health_number', '123456789012', 'No. BPJS Kesehatan harus 13 digit angka.'],
    'BPJS Ketenagakerjaan of 13 digits' => ['bpjs_employment_number', '1234567890123', 'No. BPJS Ketenagakerjaan harus 11 digit angka.'],
    'account number of five digits' => ['bank_account_number', '12345', 'No. rekening harus 6–20 digit angka.'],
    'landline as a phone' => ['phone', '0215551234', 'Telepon harus nomor HP yang diawali 08 (10–13 digit).'],
    'phone that is too short' => ['phone', '08123', 'Telepon harus nomor HP yang diawali 08 (10–13 digit).'],
    'name with digits' => ['full_name', 'Budi 123', 'Nama hanya boleh huruf, spasi, titik, apostrof, dan tanda hubung.'],
    'ID with a space' => ['employee_number', 'EMP 01', 'ID karyawan hanya boleh huruf, angka, dan tanda hubung, tanpa spasi.'],
    'a bank not on the list' => ['bank_name', 'Bank Antah Berantah', 'Pilih bank dari daftar.'],
    'an education not on the list' => ['education_level', 'Profesor', 'Pilih pendidikan terakhir dari daftar.'],
    'a relation not on the list' => ['emergency_contact_relation', 'Tetangga', 'Pilih hubungan dari daftar.'],
    'a payment method not on the list' => ['payment_method', 'Barter', 'Pilih metode pembayaran dari daftar.'],
    'a salary of zero' => ['base_salary_idr', 0, 'Gaji pokok harus lebih dari 0.'],
    'someone under 17' => ['birth_date', now()->subYears(16)->toDateString(), 'Usia karyawan harus antara 17 dan 70 tahun.'],
    'someone over 70' => ['birth_date', now()->subYears(71)->toDateString(), 'Usia karyawan harus antara 17 dan 70 tahun.'],
    'a start date over a year ahead' => ['joined_on', now()->addYears(2)->toDateString(), 'Tanggal bergabung tidak boleh lebih dari 1 tahun ke depan.'],
]);

it('refuses an emergency contact who has the employee\'s own number', function () {
    saveProfile(['phone' => '081234567890', 'emergency_contact_phone' => '+62 812 3456 7890'])
        ->assertSessionHasErrors(['emergency_contact_phone' => 'Telepon darurat tidak boleh sama dengan telepon karyawan.']);
});

it('keeps the PTKP status in step with the marital status', function () {
    saveProfile(['marital_status' => 'married', 'ptkp_status' => 'TK/0'])->assertSessionHasErrors('ptkp_status');
    saveProfile(['marital_status' => 'single', 'ptkp_status' => 'K/1'])->assertSessionHasErrors('ptkp_status');
    saveProfile(['marital_status' => 'divorced', 'ptkp_status' => 'TK/2'])->assertSessionDoesntHaveErrors();
    saveProfile(['employee_number' => 'EMP-5002', 'national_id' => '3174000000005002', 'marital_status' => 'married', 'ptkp_status' => 'K/I/2'])
        ->assertSessionDoesntHaveErrors();
});

it('lets one person per company have no manager, and asks everyone else for one', function () {
    // The form's own manager record is made here, and given a manager, so nobody but the new profile is at the top.
    profileForm();
    DB::table('employees')->update(['manager_id' => DB::raw('id')]);

    saveProfile(['manager_id' => null, 'no_manager' => '1'])->assertSessionDoesntHaveErrors();

    expect(Employee::firstWhere('employee_number', 'EMP-5001')->manager_id)->toBeNull();

    saveProfile(['employee_number' => 'EMP-5002', 'national_id' => '3174000000005002', 'manager_id' => null, 'no_manager' => '1'])
        ->assertSessionHasErrors(['manager_id' => 'Hanya satu karyawan yang boleh tanpa atasan; Budi Santoso sudah tanpa atasan.']);

    saveProfile(['employee_number' => 'EMP-5003', 'national_id' => '3174000000005003', 'manager_id' => null])
        ->assertSessionHasErrors(['manager_id' => 'Pilih atasan, atau centang "Tanpa atasan".']);
});

it('does not ask for the sensitive fields of someone who may not handle them, and does not store them', function () {
    $clerk = User::factory()->create(['company_id' => $this->company->id]);
    $clerk->givePermissionTo(Permission::CreateEmployees->value, Permission::ViewEmployees->value);

    $sensitive = [
        'bank_name', 'bank_account_number', 'bank_account_holder', 'bank_branch', 'tax_number', 'ptkp_status', 'payment_method',
        'pay_group', 'base_salary_idr', 'allowances_idr', 'overtime_rate_idr', 'family_card_number', 'mother_name',
        'bpjs_employment_number', 'bpjs_health_number',
    ];

    saveProfile(array_fill_keys($sensitive, null), $clerk)->assertSessionDoesntHaveErrors();

    $employee = Employee::firstWhere('employee_number', 'EMP-5001');

    expect($employee)->not->toBeNull()->and($employee->bank_account_number)->toBeNull()->and($employee->base_salary_idr)->toBeNull();
});

// --- Editing ---

it('keeps what an edit leaves out, and refuses a submitted field that is blank or malformed', function () {
    $employee = Employee::factory()->forCompany($this->company)->create(['phone' => '081200000001', 'bank_name' => 'BCA']);
    $edit = fn (array $fields) => $this->actingAs($this->hr)->from(route('employees.edit', $employee))->put(route('employees.update', $employee), [
        'employee_number' => $employee->employee_number,
        'national_id' => $employee->national_id,
        'full_name' => $employee->full_name,
        'employment_status' => 'active',
        ...$fields,
    ]);

    $edit(['job_title' => 'Kepala Gudang'])->assertSessionDoesntHaveErrors();
    expect($employee->fresh()->job_title)->toBe('Kepala Gudang')->and($employee->fresh()->phone)->toBe('081200000001');

    $edit(['phone' => ''])->assertSessionHasErrors('phone');
    $edit(['phone' => '123'])->assertSessionHasErrors('phone');
    $edit(['id_rt' => '12345'])->assertSessionHasErrors('id_rt');
    $edit(['bank_name' => 'Entah'])->assertSessionHasErrors('bank_name');

    expect($employee->fresh()->phone)->toBe('081200000001');
});
