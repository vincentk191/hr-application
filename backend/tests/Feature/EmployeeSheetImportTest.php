<?php

use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\Permission;
use App\Enums\PtkpStatus;
use App\Enums\Religion;
use App\Enums\Role;
use App\Enums\Zona;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Support\Employees\EmployeeSheet;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Loading HR's employee data sheet ("Lembar data field karyawan") from Excel (EMP-DIR-010). The CSV side of the
 * import is in EmployeeImportTest.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->pusat = Branch::factory()->forCompany($this->company)->create(['name' => 'Pusat']);
    $this->hr = User::factory()->create(['company_id' => $this->company->id]);
    $this->hr->assignRole(Role::HrAdmin->value);
    $this->warehouse = Department::factory()->forCompany($this->company)->create(['name' => 'Gudang']);
    $this->staff = Position::factory()->forDepartment($this->warehouse)->create(['name' => 'Staf']);
});

/**
 * A row of the sheet from field names, in the sheet's column order; a field left out is an empty cell.
 *
 * @param  array<string, mixed>  $values
 * @return list<mixed>
 */
function sheetRow(array $values): array
{
    return array_map(fn (array $column): mixed => $values[$column[1]] ?? null, EmployeeSheet::COLUMNS);
}

/**
 * An .xlsx with the sheet's header and the given rows. Integers become number cells and dates become Excel dates, as
 * they do when someone types them in; everything else is text.
 *
 * @param  list<list<mixed>>  $rows
 */
function employeeWorkbook(array $rows): UploadedFile
{
    $book = new Spreadsheet;
    $sheet = $book->getActiveSheet();

    foreach ([EmployeeSheet::headers(), ...$rows] as $r => $cells) {
        foreach ($cells as $c => $value) {
            $cell = Coordinate::stringFromColumnIndex($c + 1).($r + 1);

            if ($value instanceof DateTimeInterface) {
                $sheet->setCellValue($cell, ExcelDate::PHPToExcel($value));
                $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
            } elseif (is_int($value)) {
                $sheet->setCellValue($cell, $value);
            } elseif ($value !== null) {
                $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
            }
        }
    }

    $path = tempnam(sys_get_temp_dir(), 'emp').'.xlsx';
    (new Xlsx($book))->save($path);

    return new UploadedFile($path, 'karyawan.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

/**
 * @param  list<list<mixed>>  $rows
 */
function importEmployeeWorkbook(array $rows, ?User $by = null, bool $dryRun = false): TestResponse
{
    return test()->actingAs($by ?? test()->hr)->post(route('employees.import.store'), [
        'file' => employeeWorkbook($rows),
        'dry_run' => $dryRun ? '1' : null,
    ]);
}

/**
 * @return array<string, mixed>
 */
function sheetImportResult(): array
{
    return session('employee_import_result');
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function budiOnTheSheet(array $overrides = []): array
{
    return [
        'company' => test()->company->name,
        'company_npwp_15' => '01.234.567.8-901.000',
        'company_npwp_22' => '0123456789012000000000',
        'employee_number' => 'K-1',
        'full_name' => 'Budi Santoso',
        'gender' => 'Laki-laki',
        'branch' => 'Pusat',
        'ptkp_status' => 'K/1',
        'joined_on' => new DateTimeImmutable('2024-01-15'),
        'birth_place' => 'Surabaya',
        'birth_date' => new DateTimeImmutable('1990-05-20'),
        'religion' => 'Islam',
        'marital_status' => 'Kawin',
        'address' => 'Jl. Contoh No. 1',
        'residence_village' => 'Menteng',
        'residence_district' => 'Menteng',
        'residence_city' => 'Jakarta Pusat',
        'residence_province' => 'DKI Jakarta',
        'residence_postal_code' => '10310',
        'family_card_number' => '3174000000000002',
        // Typed as a number, as Excel keeps it: it must not come back as 3,17E+15.
        'national_id' => 3174000000000001,
        'mother_name' => 'Siti Aminah',
        'id_address' => 'Jl. KTP No. 9',
        'id_block' => 'B',
        'id_house_number' => '9',
        'id_rt' => '001',
        'id_rw' => '002',
        'id_village' => 'Gambir',
        'id_district' => 'Gambir',
        'id_city' => 'Jakarta Pusat',
        'id_province' => 'DKI Jakarta',
        'id_postal_code' => '10110',
        'department' => 'Gudang',
        'job_title' => 'Supervisor Gudang',
        'position' => 'Staf',
        'bank_account_number' => '1234567890',
        'bank_account_holder' => 'Budi Santoso',
        'bank_name' => 'BCA',
        'bank_branch' => 'KCP Menteng',
        'tax_number' => '01.234.567.8-901.000',
        // A number cell, so its leading zero is gone: 081200000000 arrives as 81200000000.
        'phone' => 81200000000,
        'bpjs_employment_number' => '12345678901',
        'bpjs_health_number' => '0001234567890',
        'employee_insurance' => 'Allianz',
        'email' => 'budi@example.com',
        'employment_type' => 'Kontrak',
        'education_level' => 'S1',
        'zona' => 'Merah',
        ...$overrides,
    ];
}

it('loads HR\'s data sheet from Excel: every column lands, dates and long numbers survive', function () {
    importEmployeeWorkbook([sheetRow(budiOnTheSheet())])->assertRedirect(route('employees.import'));

    expect(sheetImportResult())->toMatchArray(['total' => 1, 'created' => 1, 'rejected' => 0, 'sensitiveSkipped' => false]);

    $budi = Employee::where('employee_number', 'K-1')->firstOrFail();

    expect($budi->full_name)->toBe('Budi Santoso')
        ->and($budi->gender)->toBe(Gender::Male)
        ->and($budi->branch_id)->toBe($this->pusat->id)
        ->and($budi->department_id)->toBe($this->warehouse->id)
        ->and($budi->position_id)->toBe($this->staff->id)
        ->and($budi->job_title)->toBe('Supervisor Gudang')
        ->and($budi->employment_type)->toBe(EmploymentType::Contract)
        ->and($budi->zona)->toBe(Zona::Merah)
        ->and($budi->joined_on->toDateString())->toBe('2024-01-15')
        ->and($budi->birth_date->toDateString())->toBe('1990-05-20')
        ->and($budi->birth_place)->toBe('Surabaya')
        ->and($budi->religion)->toBe(Religion::Islam)
        ->and($budi->marital_status)->toBe(MaritalStatus::Married)
        ->and($budi->education_level)->toBe('S1')
        ->and($budi->national_id)->toBe('3174000000000001')
        ->and($budi->phone)->toBe('081200000000')
        ->and($budi->email)->toBe('budi@example.com')
        ->and($budi->address)->toBe('Jl. Contoh No. 1')
        ->and([$budi->residence_village, $budi->residence_district, $budi->residence_city, $budi->residence_province, $budi->residence_postal_code])
        ->toBe(['Menteng', 'Menteng', 'Jakarta Pusat', 'DKI Jakarta', '10310'])
        // The second "No KTP" column is the house number of the KTP address, not a second NIK.
        ->and([$budi->id_address, $budi->id_block, $budi->id_house_number, $budi->id_rt, $budi->id_rw])
        ->toBe(['Jl. KTP No. 9', 'B', '9', '001', '002'])
        ->and([$budi->id_village, $budi->id_district, $budi->id_city, $budi->id_province, $budi->id_postal_code])
        ->toBe(['Gambir', 'Gambir', 'Jakarta Pusat', 'DKI Jakarta', '10110'])
        ->and([$budi->family_card_number, $budi->mother_name, $budi->ptkp_status])
        ->toBe(['3174000000000002', 'Siti Aminah', PtkpStatus::K1])
        ->and([$budi->bank_name, $budi->bank_account_number, $budi->bank_account_holder, $budi->bank_branch])
        ->toBe(['BCA', '1234567890', 'Budi Santoso', 'KCP Menteng'])
        // Dots and dashes are read as the digits they are around.
        ->and($budi->tax_number)->toBe('012345678901000')
        ->and([$budi->bpjs_employment_number, $budi->bpjs_health_number, $budi->employee_insurance])
        ->toBe(['12345678901', '0001234567890', 'Allianz']);
});

it('reads the sensitive columns only for someone who may handle them, and says when it skipped them', function () {
    $clerk = User::factory()->create(['company_id' => $this->company->id]);
    $clerk->givePermissionTo(Permission::CreateEmployees->value);

    importEmployeeWorkbook([sheetRow(budiOnTheSheet())], $clerk);

    expect(sheetImportResult())->toMatchArray(['created' => 1, 'sensitiveSkipped' => true]);

    $budi = Employee::where('employee_number', 'K-1')->firstOrFail();

    expect($budi->gender)->toBe(Gender::Male)
        ->and($budi->birth_place)->toBe('Surabaya')
        ->and($budi->id_address)->toBe('Jl. KTP No. 9')
        ->and($budi->national_id)->toBe('3174000000000001')
        ->and([$budi->bank_name, $budi->bank_account_number, $budi->tax_number, $budi->family_card_number, $budi->mother_name])->each->toBeNull()
        ->and([$budi->ptkp_status, $budi->bpjs_health_number, $budi->employee_insurance])->each->toBeNull();
});

it('refuses a sheet that names another company, and fills the company\'s NPWP only while it has none', function () {
    importEmployeeWorkbook([
        sheetRow(budiOnTheSheet(['company' => 'PT Perusahaan Lain Sekali'])),
        sheetRow(budiOnTheSheet(['employee_number' => 'K-2', 'full_name' => 'Siti', 'national_id' => 3174000000000005])),
    ]);

    expect(sheetImportResult())->toMatchArray(['created' => 1, 'rejected' => 1])
        ->and(sheetImportResult()['errors'][0])->toContain('Baris 2')->toContain('bukan perusahaan yang dipilih');

    $this->company->refresh();
    expect($this->company->npwp_15)->toBe('012345678901000')->and($this->company->npwp_22)->toBe('0123456789012000000000');

    $this->company->update(['npwp_15' => '999999999999999']);
    importEmployeeWorkbook([sheetRow(budiOnTheSheet(['employee_number' => 'K-3', 'national_id' => 3174000000000006]))]);

    expect($this->company->fresh()->npwp_15)->toBe('999999999999999');
});

it('accepts a company name without PT, and a dry run changes nothing, company included', function () {
    $this->company->update(['name' => 'PT Karya Logistik']);

    importEmployeeWorkbook([sheetRow(budiOnTheSheet(['company' => 'Karya Logistik']))], dryRun: true);

    expect(sheetImportResult())->toMatchArray(['created' => 1, 'rejected' => 0, 'dryRun' => true])
        ->and(Employee::count())->toBe(0)
        ->and($this->company->fresh()->npwp_15)->toBeNull();
});

it('finds the Supervisor by employee number or by full name, and refuses a name two people share', function () {
    Employee::factory()->forCompany($this->company)->create(['employee_number' => 'BOSS', 'full_name' => 'Pak Bos']);
    Employee::factory()->forCompany($this->company)->create(['employee_number' => 'D-1', 'full_name' => 'Dewi Kembar']);
    Employee::factory()->forCompany($this->company)->create(['employee_number' => 'D-2', 'full_name' => 'dewi  kembar']);

    importEmployeeWorkbook([
        sheetRow(budiOnTheSheet(['employee_number' => 'K-1', 'full_name' => 'Satu', 'national_id' => 3174000000000011, 'manager' => 'pak bos'])),
        sheetRow(budiOnTheSheet(['employee_number' => 'K-2', 'full_name' => 'Dua', 'national_id' => 3174000000000012, 'manager' => 'BOSS'])),
        sheetRow(budiOnTheSheet(['employee_number' => 'K-3', 'full_name' => 'Tiga', 'national_id' => 3174000000000013, 'manager' => 'Satu'])),
        sheetRow(budiOnTheSheet(['employee_number' => 'K-4', 'full_name' => 'Empat', 'national_id' => 3174000000000014, 'manager' => 'Dewi Kembar'])),
    ]);

    $boss = Employee::where('employee_number', 'BOSS')->value('id');

    expect(sheetImportResult())->toMatchArray(['created' => 3, 'rejected' => 1])
        ->and(sheetImportResult()['errors'][0])->toContain('Baris 5')->toContain('lebih dari satu karyawan')
        ->and(Employee::where('employee_number', 'K-1')->value('manager_id'))->toBe($boss)
        ->and(Employee::where('employee_number', 'K-2')->value('manager_id'))->toBe($boss)
        ->and(Employee::where('employee_number', 'K-3')->value('manager_id'))->toBe(Employee::where('employee_number', 'K-1')->value('id'));
});

it('rejects values the sheet cannot be read from, each with its line', function () {
    importEmployeeWorkbook([
        sheetRow(budiOnTheSheet(['employee_number' => 'K-1', 'national_id' => 3174000000000021, 'gender' => 'Entah'])),
        sheetRow(budiOnTheSheet(['employee_number' => 'K-2', 'national_id' => 3174000000000022, 'ptkp_status' => 'ZZ/9'])),
        sheetRow(budiOnTheSheet(['employee_number' => 'K-3', 'national_id' => 3174000000000023, 'family_card_number' => '12345'])),
        sheetRow(budiOnTheSheet(['employee_number' => 'K-4', 'national_id' => 3174000000000024, 'tax_number' => '123'])),
        sheetRow(budiOnTheSheet(['employee_number' => 'K-5', 'national_id' => 3174000000000025, 'birth_date' => new DateTimeImmutable('+1 year')])),
        sheetRow(budiOnTheSheet(['employee_number' => 'K-6', 'national_id' => 3174000000000026, 'position' => 'Tidak Ada'])),
        sheetRow(budiOnTheSheet(['employee_number' => 'K-7', 'national_id' => 3174000000000027, 'zona' => 'Biru'])),
        sheetRow(budiOnTheSheet(['employee_number' => 'K-8', 'national_id' => 3174000000000028])),
    ]);

    $errors = implode(' | ', sheetImportResult()['errors']);

    expect(sheetImportResult())->toMatchArray(['created' => 1, 'rejected' => 7])
        ->and($errors)->toContain('Baris 2: jenis kelamin "Entah"')
        ->toContain('Baris 3: status PTKP "ZZ/9"')
        ->toContain('Baris 4: No KK harus 16 digit')
        ->toContain('Baris 5: NPWP 16 harus 15 atau 16 digit')
        ->toContain('Baris 6: tanggal lahir harus sebelum hari ini')
        ->toContain('Baris 7: posisi "Tidak Ada" belum ada')
        ->toContain('Baris 8: zona "Biru"');
});

it('picks the position under the row\'s Divisi when the same name exists in several departments', function () {
    $office = Department::factory()->forCompany($this->company)->create(['name' => 'Kantor']);
    $officeStaff = Position::factory()->forDepartment($office)->create(['name' => 'Staf']);

    importEmployeeWorkbook([
        sheetRow(budiOnTheSheet(['employee_number' => 'K-1', 'national_id' => 3174000000000031, 'department' => 'Kantor'])),
        sheetRow(budiOnTheSheet(['employee_number' => 'K-2', 'national_id' => 3174000000000032, 'department' => null])),
    ]);

    expect(sheetImportResult())->toMatchArray(['created' => 1, 'rejected' => 1])
        ->and(Employee::where('employee_number', 'K-1')->value('position_id'))->toBe($officeStaff->id)
        ->and(sheetImportResult()['errors'][0])->toContain('lebih dari satu departemen');
});

it('refuses a file that claims to be Excel but is not', function () {
    $file = UploadedFile::fake()->createWithContent('karyawan.xlsx', 'PK-ini-bukan-excel');

    $this->actingAs($this->hr)->post(route('employees.import.store'), ['file' => $file])->assertSessionHasErrors('file');
    expect(Employee::count())->toBe(0);
});
