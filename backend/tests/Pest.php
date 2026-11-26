<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * A complete, valid employee form: every field a new profile needs (the profile form requires nearly all of them). The records
 * it points at (department, position, manager) are made in `test()->company`, so a test that posts to another company says
 * so with its own ids. A test adds its own keys after it: the employee number, NIK, name and Cabang, which differ per test.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function fullEmployeeForm(array $overrides = []): array
{
    $test = test();
    $company = $test->company;

    // Made once per test and company, so a test that builds the form several times adds no further records.
    static $made = [];
    $key = $test->name().'|'.$company->id;

    $records = $made[$key] ??= (function () use ($company): array {
        $department = Department::factory()->create(['company_id' => $company->id]);

        return [
            'department_id' => $department->id,
            'position_id' => Position::factory()->forDepartment($department)->create()->id,
            'manager_id' => Employee::factory()->forCompany($company)->create()->id,
        ];
    })();

    // Photos are stored: on a fake disk, unless the test already put one there (its files must stay).
    if (! str_contains(Storage::disk(Employee::PHOTO_DISK)->path(''), 'testing')) {
        Storage::fake(Employee::PHOTO_DISK);
    }

    return [
        'photo' => UploadedFile::fake()->image('foto.jpg', 200, 200),
        ...$records,
        'job_title' => 'Staf Gudang',
        'employment_type' => 'permanent',
        'notice_days' => 30,
        'joined_on' => '2025-01-02',
        'gender' => 'male',
        'birth_place' => 'Jakarta',
        'birth_date' => '1990-05-20',
        'religion' => 'islam',
        'marital_status' => 'single',
        'education_level' => 'S1',
        'email' => 'karyawan@example.test',
        'phone' => '081234567890',
        'address' => 'Jl. Contoh No. 1',
        'emergency_contact_name' => 'Siti Aminah',
        'emergency_contact_phone' => '081298765432',
        'emergency_contact_relation' => 'Orang tua',
        'residence_village' => 'Menteng',
        'residence_district' => 'Menteng',
        'residence_city' => 'Jakarta Pusat',
        'residence_province' => 'DKI Jakarta',
        'residence_postal_code' => '10310',
        'id_address' => 'Jl. KTP No. 9',
        'id_house_number' => '9',
        'id_rt' => '001',
        'id_rw' => '002',
        'id_village' => 'Menteng',
        'id_district' => 'Menteng',
        'id_city' => 'Jakarta Pusat',
        'id_province' => 'DKI Jakarta',
        'id_postal_code' => '10110',
        'family_card_number' => '3174000000000001',
        'mother_name' => 'Siti Aminah',
        'ptkp_status' => 'TK/0',
        'bank_name' => 'BCA',
        'bank_account_number' => '1234567890',
        'bank_account_holder' => 'Budi Santoso',
        'bank_branch' => 'KCP Menteng',
        'tax_number' => '3174000000000001',
        'payment_method' => 'Transfer',
        'bpjs_employment_number' => '12345678901',
        'bpjs_health_number' => '0001234567890',
        'pay_group' => 'Bulanan',
        'base_salary_idr' => 7500000,
        'allowances_idr' => 500000,
        'overtime_rate_idr' => 25000,
        ...$overrides,
    ];
}
