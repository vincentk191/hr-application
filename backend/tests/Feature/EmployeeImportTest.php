<?php

use App\Enums\Role;
use App\Http\Middleware\SetActiveCompany;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;

/**
 * Directory bulk import and export (EMP-DIR-010, EMP-DIR-011).
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->other = Company::factory()->create();
    $this->hr = User::factory()->create(['company_id' => $this->company->id]);
    $this->hr->assignRole(Role::HrAdmin->value);
    $this->warehouse = Department::factory()->forCompany($this->company)->create(['name' => 'Gudang']);
    $this->staff = Position::factory()->forDepartment($this->warehouse)->create(['name' => 'Staf']);
});

function employeeCsv(string $body): UploadedFile
{
    return UploadedFile::fake()->createWithContent('karyawan.csv', $body);
}

/**
 * Most of these tests are about something other than NIK, which a row must now have. Unless the file
 * brings its own NIK column, give every row a unique one so those tests stay about what they say.
 */
function withNiks(string $body): string
{
    static $next = 3174000000000000;

    $lines = preg_split('/\r?\n/', $body) ?: [];
    $header = $lines[0] ?? '';

    if (stripos($header, 'nik') !== false) {
        return $body;
    }

    $delimiter = substr_count($header, ';') > substr_count($header, ',') ? ';' : ',';
    $out = [$header.$delimiter.'NIK'];

    foreach (array_slice($lines, 1) as $line) {
        $out[] = trim($line) === '' ? $line : $line.$delimiter.(++$next);
    }

    return implode("\n", $out);
}

function importEmployeesCsv(string $body, bool $dryRun = false): TestResponse
{
    return test()->actingAs(test()->hr)->post(route('employees.import.store'), [
        'file' => employeeCsv(withNiks($body)),
        'dry_run' => $dryRun ? '1' : null,
    ]);
}

/**
 * @return array<string, mixed>
 */
function employeeImportResult(): array
{
    return session('employee_import_result');
}

it('creates employees from a CSV, matching columns by header name', function () {
    importEmployeesCsv("ID karyawan,Nama,Departemen,Jabatan,Status,Tipe,Tanggal bergabung,Email\nK-1,Budi,Gudang,Staf,Aktif,Kontrak,15/01/2024,budi@example.com\nK-2,Siti,,,,,2024-02-01,\n")
        ->assertRedirect(route('employees.import'));

    expect(employeeImportResult())->toMatchArray(['total' => 2, 'created' => 2, 'duplicates' => 0, 'rejected' => 0]);

    $budi = Employee::where('employee_number', 'K-1')->firstOrFail();
    expect($budi->company_id)->toBe($this->company->id)
        ->and($budi->department_id)->toBe($this->warehouse->id)
        ->and($budi->position_id)->toBe($this->staff->id)
        ->and($budi->employment_type->value)->toBe('contract')
        ->and($budi->joined_on->toDateString())->toBe('2024-01-15');
    expect(Employee::where('employee_number', 'K-2')->firstOrFail()->joined_on->toDateString())->toBe('2024-02-01');
});

it('accepts Indonesian headers and a semicolon delimiter', function () {
    importEmployeesCsv("NIP;Nama Lengkap;Departemen\nK-9;Dewi;Gudang\n");

    expect(employeeImportResult()['created'])->toBe(1)
        ->and(Employee::where('employee_number', 'K-9')->first()->department_id)->toBe($this->warehouse->id);
});

it('skips an employee number that already exists, without changing the record', function () {
    Employee::factory()->forCompany($this->company)->create(['employee_number' => 'K-1', 'full_name' => 'Asli']);

    importEmployeesCsv("ID,Nama\nk-1,Diganti\nK-3,Baru\nK-3,Kembar\n");

    expect(employeeImportResult())->toMatchArray(['created' => 1, 'duplicates' => 2])
        ->and(Employee::where('employee_number', 'K-1')->value('full_name'))->toBe('Asli')
        ->and(Employee::where('employee_number', 'K-3')->value('full_name'))->toBe('Baru');
});

it('may reuse an employee number that another company has', function () {
    Employee::factory()->forCompany($this->other)->create(['employee_number' => 'K-1']);

    importEmployeesCsv("ID,Nama\nK-1,Budi\n");

    expect(employeeImportResult()['created'])->toBe(1);
});

it('rejects bad rows with their line number and still loads the good ones', function () {
    importEmployeesCsv("ID,Nama,Departemen,Status,Email,Tanggal bergabung\nK-1,Budi,Tidak Ada,,,\nK-2,Siti,,Entah,,\nK-3,Joko,,,bukan-email,\nK-4,Rina,,,,31-31-2024\nK-5,Tono,Gudang,,,\n,Tanpa ID,,,,\n");

    $result = employeeImportResult();

    expect($result['created'])->toBe(1)
        ->and($result['rejected'])->toBe(5)
        ->and($result['errors'][0])->toStartWith('Baris 2:')
        ->and(implode(' ', $result['errors']))->toContain('departemen', 'status', 'email', 'tanggal bergabung', 'ID karyawan');
    expect(Employee::where('employee_number', 'K-5')->exists())->toBeTrue();
});

it('rejects an exit date before the join date', function () {
    importEmployeesCsv("ID,Nama,Tanggal bergabung,Tanggal keluar\nK-1,Budi,2024-05-01,2024-04-01\n");

    expect(employeeImportResult())->toMatchArray(['created' => 0, 'rejected' => 1]);
});

it('resolves managers already in the company and elsewhere in the file, in any order', function () {
    $boss = Employee::factory()->forCompany($this->company)->create(['employee_number' => 'BOSS']);

    importEmployeesCsv("ID,Nama,ID atasan\nK-2,Anak,K-1\nK-1,Tengah,BOSS\n");

    expect(employeeImportResult()['created'])->toBe(2)
        ->and(Employee::where('employee_number', 'K-1')->value('manager_id'))->toBe($boss->id)
        ->and(Employee::where('employee_number', 'K-2')->value('manager_id'))->toBe(Employee::where('employee_number', 'K-1')->value('id'));
});

it('rejects an unknown manager, a self-reference and a loop, and anyone stranded by a rejection', function () {
    importEmployeesCsv("ID,Nama,ID atasan\nA,Satu,NOPE\nB,Dua,B\nC,Tiga,D\nD,Empat,C\nE,Lima,A\nF,Enam,\n");

    $result = employeeImportResult();

    expect($result['created'])->toBe(1)
        ->and($result['rejected'])->toBe(5)
        ->and(Employee::pluck('employee_number')->all())->toBe(['F']);
});

it('writes nothing on a dry run but reports what would happen', function () {
    importEmployeesCsv("ID,Nama\nK-1,Budi\nK-2,\n", dryRun: true);

    expect(employeeImportResult())->toMatchArray(['created' => 1, 'rejected' => 1, 'dryRun' => true])
        ->and(Employee::count())->toBe(0);
});

it('ignores sensitive columns', function () {
    importEmployeesCsv("ID,Nama,Gaji,Bank\nK-1,Budi,9000000,BCA\n");

    $employee = Employee::firstOrFail();
    expect($employee->base_salary_idr)->toBeNull()->and($employee->bank_name)->toBeNull();
});

it('refuses a file without an ID or name column, and an empty one', function () {
    importEmployeesCsv("Nama,Email\nBudi,b@example.com\n")->assertSessionHasErrors('file');
    importEmployeesCsv("ID,Email\nK-1,b@example.com\n")->assertSessionHasErrors('file');
    importEmployeesCsv('')->assertSessionHasErrors('file');
    expect(Employee::count())->toBe(0);
});

it('refuses more rows than the limit', function () {
    $body = "ID,Nama\n".implode("\n", array_map(fn (int $n) => "K-{$n},Nama {$n}", range(1, 2001)))."\n";

    importEmployeesCsv($body)->assertSessionHasErrors('file');
    expect(Employee::count())->toBe(0);
});

it('needs the create permission, and an active company', function () {
    $viewer = User::factory()->create(['company_id' => $this->company->id]);
    $viewer->assignRole(Role::Employee->value);

    $this->actingAs($viewer)->post(route('employees.import.store'), ['file' => employeeCsv("ID,Nama\nK-1,Budi\n")])->assertForbidden();
    $this->actingAs($viewer)->get(route('employees.import'))->assertForbidden();
    $this->actingAs($viewer)->get(route('employees.import.template'))->assertForbidden();

    $group = User::factory()->create(['company_id' => null]);
    $group->assignRole(Role::HrAdmin->value);

    $this->actingAs($group)->withSession([SetActiveCompany::SESSION_KEY => SetActiveCompany::COMBINED])
        ->post(route('employees.import.store'), ['file' => employeeCsv("ID,Nama\nK-1,Budi\n")])
        ->assertSessionHasErrors('file');
    expect(Employee::acrossCompanies()->count())->toBe(0);
});

it('imports into the company a group-wide user has selected', function () {
    $group = User::factory()->create(['company_id' => null]);
    $group->assignRole(Role::HrAdmin->value);

    $this->actingAs($group)->withSession([SetActiveCompany::SESSION_KEY => (string) $this->other->id])
        ->post(route('employees.import.store'), ['file' => employeeCsv(withNiks("ID,Nama\nK-1,Budi\n"))]);

    expect(Employee::acrossCompanies()->where('employee_number', 'K-1')->value('company_id'))->toBe($this->other->id);
});

it('requires a 16-digit NIK on every row', function () {
    importEmployeesCsv("ID,Nama,NIK\nK-1,Budi,\nK-2,Siti,12345\nK-3,Dewi,3174000000000003\n");

    expect(employeeImportResult())->toMatchArray(['created' => 1, 'rejected' => 2])
        ->and(employeeImportResult()['errors'][0])->toContain('Baris 2')->toContain('NIK kosong')
        ->and(employeeImportResult()['errors'][1])->toContain('Baris 3')->toContain('16 digit');
});

it('refuses a NIK that another employee in any company, or an earlier row, already has', function () {
    Employee::factory()->forCompany($this->other)->create(['national_id' => '3174000000000001']);

    importEmployeesCsv("ID,Nama,NIK\nK-1,Budi,3174000000000001\nK-2,Siti,3174000000000002\nK-3,Dewi,3174000000000002\n");

    expect(employeeImportResult())->toMatchArray(['created' => 1, 'rejected' => 2])
        ->and(employeeImportResult()['errors'][0])->toContain('NIK 3174000000000001 sudah terdaftar')
        ->and(Employee::where('employee_number', 'K-2')->exists())->toBeTrue()
        ->and(Employee::where('employee_number', 'K-3')->exists())->toBeFalse();
});

it('records the import on the audit trail', function () {
    importEmployeesCsv("ID,Nama\nK-1,Budi\n");

    expect(Activity::where('description', 'Impor karyawan dari CSV')->count())->toBe(1);
});

it('serves a template with the recognised columns', function () {
    $response = $this->actingAs($this->hr)->get(route('employees.import.template'))->assertOk();

    expect($response->streamedContent())->toContain('ID karyawan', 'Tanggal bergabung');
});

it('exports the filtered directory, matches the import columns and leaves out sensitive fields', function () {
    $boss = Employee::factory()->forCompany($this->company)->create(['employee_number' => 'BOSS', 'full_name' => 'Bos']);
    Employee::factory()->inDepartment($this->warehouse)->create([
        'employee_number' => 'K-1', 'full_name' => 'Budi', 'manager_id' => $boss->id, 'base_salary_idr' => 7777777, 'bank_name' => 'BCA',
    ]);
    Employee::factory()->forCompany($this->company)->create(['employee_number' => 'K-2', 'full_name' => 'Siti']);
    Employee::factory()->forCompany($this->other)->create(['employee_number' => 'OTHER']);

    $all = $this->actingAs($this->hr)->get(route('employees.export'))->assertOk()->streamedContent();
    expect($all)->toContain('K-1', 'K-2', 'BOSS', 'ID karyawan')->not->toContain('OTHER')->not->toContain('7777777')->not->toContain('BCA');

    $filtered = $this->actingAs($this->hr)->get(route('employees.export', ['departments' => [$this->warehouse->name]]))->streamedContent();
    expect($filtered)->toContain('K-1', 'Gudang')->not->toContain('K-2');
    expect(Activity::where('description', 'Ekspor direktori karyawan ke CSV')->count())->toBe(2);
});

it('exports a file the importer can read back', function () {
    $boss = Employee::factory()->inDepartment($this->warehouse)->create(['employee_number' => 'BOSS', 'full_name' => 'Bos', 'joined_on' => '2020-03-04']);
    Employee::factory()->inDepartment($this->warehouse)->create(['employee_number' => 'K-1', 'full_name' => 'Budi', 'manager_id' => $boss->id, 'joined_on' => '2022-01-02']);

    $csv = $this->actingAs($this->hr)->get(route('employees.export'))->streamedContent();

    // NIK is unique across the group, so the people exported must no longer hold theirs.
    Employee::query()->update(['national_id' => null]);

    $second = User::factory()->create(['company_id' => $this->other->id]);
    $second->assignRole(Role::HrAdmin->value);
    Department::factory()->forCompany($this->other)->create(['name' => 'Gudang']);

    $this->actingAs($second)->post(route('employees.import.store'), ['file' => employeeCsv($csv)]);

    expect(employeeImportResult())->toMatchArray(['created' => 2, 'rejected' => 0])
        ->and(Employee::acrossCompanies()->where('company_id', $this->other->id)->where('employee_number', 'K-1')->value('joined_on'))->not->toBeNull();
});

it('does not let an export cell run as a spreadsheet formula', function () {
    Employee::factory()->forCompany($this->company)->create(['employee_number' => 'K-1', 'full_name' => '=HYPERLINK("x")']);

    $csv = $this->actingAs($this->hr)->get(route('employees.export'))->streamedContent();

    expect($csv)->toContain("'=HYPERLINK");
});
