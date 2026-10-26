<?php

use App\Actions\Attendance\IngestPunches;
use App\Actions\Attendance\MapEnrolId;
use App\Enums\Role;
use App\Models\AttendanceEnrolMap;
use App\Models\AttendanceImport;
use App\Models\AttendancePunch;
use App\Models\AttendanceUnmappedPunch;
use App\Models\CalendarEvent;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Support\Attendance\CaptureStatus;
use App\Support\Attendance\InvalidScanLog;
use App\Support\Attendance\ScanLogParser;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Loading a scanner log by hand, and what happens to each punch in it (ATT-DEV).
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['attendance.device_timezone' => 'Asia/Jakarta']);
    $this->company = Company::factory()->create();
    $this->hr = attendanceUser(Role::HrAdmin);
    $this->employee = Employee::factory()->forCompany($this->company)->create(['employee_number' => '1042']);
});

function attendanceUser(Role $role, ?Company $company = null): User
{
    $user = User::factory()->create(['company_id' => $company?->id]);
    $user->assignRole($role->value);

    return $user;
}

function csv(string ...$lines): UploadedFile
{
    return UploadedFile::fake()->createWithContent('log.csv', implode("\n", $lines)."\n");
}

/**
 * Parse CSV text directly. The temporary file is kept alive for the duration of the parse; a
 * fake UploadedFile deletes its file as soon as the object is released.
 */
function parseCsv(string ...$lines): array
{
    $path = tempnam(sys_get_temp_dir(), 'scanlog');
    file_put_contents($path, implode('
', $lines).'
');

    try {
        return app(ScanLogParser::class)->parse($path, 'Asia/Jakarta');
    } finally {
        unlink($path);
    }
}

function fingerspotExport(): UploadedFile
{
    return new UploadedFile(base_path('tests/Fixtures/fingerspot-export.xlsx'), 'export laporan.xlsx', null, null, true);
}

/**
 * The Fingerspot daily report for Friday 23-10-2026: nine employee-days, six with scans (one of them left early) and
 * three without (absent, maternity leave, a business trip).
 */
function fingerspotDailyReport(): UploadedFile
{
    return new UploadedFile(base_path('tests/Fixtures/fingerspot-laporan-harian.xlsx'), '20261023 ABSESI FINGERSPOT.xlsx', null, null, true);
}

/**
 * @param  list<list<mixed>>  $rows
 */
function xlsx(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1', true);
    $path = tempnam(sys_get_temp_dir(), 'scanlog').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'log.xlsx', null, null, true);
}

function mapPin(string $pin, Employee $employee, string $sn = 'SN1', ?string $from = null, ?string $to = null): void
{
    app(MapEnrolId::class)->handle(
        $sn,
        $pin,
        $employee,
        $from === null ? null : CarbonImmutable::parse($from),
        $to === null ? null : CarbonImmutable::parse($to),
    );
}

// --- Parsing ---

it('reads the canonical columns and converts device time to UTC', function () {
    $parsed = parseCsv('pin,scan_at,verify_mode,inout_mode', '1042,2026-10-05 07:55:10,1,0');

    expect($parsed['rows'])->toHaveCount(1)
        ->and($parsed['rows'][0]['pin'])->toBe('1042')
        ->and($parsed['rows'][0]['scan_at']->toDateTimeString())->toBe('2026-10-05 00:55:10')
        ->and($parsed['rows'][0]['verify_mode'])->toBe(1)
        ->and($parsed['rows'][0]['inout_mode'])->toBe(0);
});

it('recognises other header names, delimiters and day-first dates', function () {
    $parsed = parseCsv('No. ID;Waktu;SN', '1042;05/10/2026 07:55:10;ABC123', '7;06-10-2026 17:01;ABC123');

    expect($parsed['rows'])->toHaveCount(2)
        ->and($parsed['rows'][0]['sn'])->toBe('ABC123')
        ->and($parsed['rows'][0]['scan_at']->toDateTimeString())->toBe('2026-10-05 00:55:10')
        ->and($parsed['rows'][1]['scan_at']->toDateTimeString())->toBe('2026-10-06 10:01:00');
});

it('combines separate date and time columns', function () {
    $parsed = parseCsv("ID\tTanggal\tJam", "1042\t05/10/2026\t07:55:10");

    expect($parsed['rows'][0]['scan_at']->toDateTimeString())->toBe('2026-10-05 00:55:10');
});

it('reads a spreadsheet-mangled PIN and a byte-order mark', function () {
    $parsed = parseCsv("\xEF\xBB\xBFpin,scan_at", '1042.0,2026-10-05 07:55:10');

    expect($parsed['rows'][0]['pin'])->toBe('1042');
});

it('counts bad rows instead of failing the whole file', function () {
    $parsed = parseCsv('pin,scan_at', '1042,2026-10-05 07:55:10', ',2026-10-05 08:00:00', '1043,bukan tanggal', '1044,2026-13-45 07:00:00');

    expect($parsed['total'])->toBe(4)
        ->and($parsed['rows'])->toHaveCount(1)
        ->and($parsed['errors'])->toHaveCount(3)
        ->and($parsed['errors'][0])->toContain('Baris 3');
});

it('refuses a file with no recognisable PIN or date column', function () {
    expect(fn () => parseCsv('nama,alamat', 'a,b'))
        ->toThrow(InvalidScanLog::class);

    expect(fn () => parseCsv('pin,nama', '1,a'))
        ->toThrow(InvalidScanLog::class);
});

it('reads the Fingerspot export laporan, title row and all', function () {
    $parsed = app(ScanLogParser::class)->parse(fingerspotExport()->getRealPath(), 'Asia/Jakarta');

    expect($parsed['total'])->toBe(28)
        ->and($parsed['errors'])->toBe([])
        ->and($parsed['rows'][0])->toMatchArray([
            'pin' => '2056',
            'nip' => '621101011225001',
            'sn' => '66208025470046',
            'verify_mode' => 1,
            'inout_mode' => 1,
        ])
        ->and($parsed['rows'][0]['scan_at']->toDateTimeString())->toBe('2026-10-12 10:07:29');
});

it('reads the Fingerspot daily report: a punch per scan, days without a scan skipped', function () {
    $parsed = app(ScanLogParser::class)->parse(fingerspotDailyReport()->getRealPath(), 'Asia/Jakarta');

    // Six employee-days with an in and an out scan. The other three carry no scan, so nothing is filed for them: the
    // dashboard works out absence from its own data, and leave from its own requests, not from Fingerspot's columns.
    expect($parsed['total'])->toBe(12)
        ->and($parsed['errors'])->toBe([])
        ->and($parsed['rows'])->toHaveCount(12)
        ->and($parsed['rows'][0])->toMatchArray([
            'pin' => '1009',
            'nip' => '141276211004006',
            'sn' => '66208025470046',
            'verify_mode' => null,
            'inout_mode' => null,
        ])
        // 07:54:15 and 17:35:55 on the scanner's clock (UTC+7).
        ->and($parsed['rows'][0]['scan_at']->toDateTimeString())->toBe('2026-10-23 00:54:15')
        ->and($parsed['rows'][1]['scan_at']->toDateTimeString())->toBe('2026-10-23 10:35:55')
        ->and(array_intersect(array_column($parsed['rows'], 'pin'), ['1039', '1068', '2006']))->toBe([]);
});

it('reads break scans from the daily report and counts a bad scan without losing the rest of its row', function () {
    $file = xlsx([
        ['Tanggal', 'PIN', 'NIP', 'Scan masuk', 'SN scan masuk', 'Scan istirahat 1', 'Scan istirahat 2', 'Scan pulang', 'SN scan pulang'],
        ['05-10-2026', '1042', 'NIP-1', '07:55:10', 'SN1', '12:01:00', '12:45:00', 'bukan jam', 'SN1'],
        ['05-10-2026', '1043', 'NIP-2', '', '', '', '', '', ''],
    ]);

    $parsed = app(ScanLogParser::class)->parse($file->getRealPath(), 'Asia/Jakarta');

    // Three good scans and one bad; the break scans have no serial of their own and take the day's.
    expect($parsed['total'])->toBe(4)
        ->and($parsed['rows'])->toHaveCount(3)
        ->and(array_unique(array_column($parsed['rows'], 'sn')))->toBe(['SN1'])
        ->and($parsed['errors'])->toHaveCount(1)
        ->and($parsed['errors'][0])->toContain('Baris 2')->toContain('Scan pulang');
});

it('refuses a daily report that has no date column', function () {
    expect(fn () => parseCsv('PIN,Scan masuk', '1042,07:55:10'))->toThrow(InvalidScanLog::class);
});

it('finds the header below title rows in a CSV', function () {
    $parsed = parseCsv('Laporan scan;;', 'Periode: Oktober;;', 'PIN;Tanggal scan;NIP', '1042;05-10-2026 07:55:10;EMP-1');

    expect($parsed['rows'])->toHaveCount(1)
        ->and($parsed['rows'][0]['nip'])->toBe('EMP-1')
        ->and($parsed['rows'][0]['scan_at']->toDateTimeString())->toBe('2026-10-05 00:55:10');
});

it('reads real Excel date and time cells', function () {
    // 46300 is 2026-10-05 in Excel's calendar; 0.33 of a day is 07:55:12.
    $file = xlsx([['PIN', 'Tanggal', 'Jam'], [1042, 46300, 0.33]]);

    $parsed = app(ScanLogParser::class)->parse($file->getRealPath(), 'Asia/Jakarta');

    expect($parsed['rows'][0]['pin'])->toBe('1042')
        ->and($parsed['rows'][0]['scan_at']->toDateTimeString())->toBe('2026-10-05 00:55:12');
});

it('reads an HTML table saved under an .xls name', function () {
    $path = tempnam(sys_get_temp_dir(), 'scanlog');
    file_put_contents($path, '<html><body><table><tr><td>PIN</td><td>Tanggal scan</td></tr><tr><td>1042</td><td>05-10-2026 07:55:10</td></tr></table></body></html>');

    try {
        $parsed = app(ScanLogParser::class)->parse($path, 'Asia/Jakarta');
    } finally {
        unlink($path);
    }

    expect($parsed['rows'])->toHaveCount(1)
        ->and($parsed['rows'][0]['scan_at']->toDateTimeString())->toBe('2026-10-05 00:55:10');
});

// --- Importing ---

it('imports the Fingerspot export without a typed serial, mapping PINs by NIP', function () {
    $staffH = Employee::factory()->forCompany($this->company)->create(['employee_number' => '621101011225001']);

    $this->actingAs($this->hr)
        ->post(route('attendance.imports.store'), ['file' => fingerspotExport()])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $import = AttendanceImport::first();

    expect($import->device_sn)->toBe('66208025470046')
        ->and($import->auto_mapped)->toBe(1)
        ->and($import->accepted)->toBe(2)
        ->and($import->unmapped)->toBe(26)
        ->and(AttendancePunch::acrossCompanies()->where('employee_id', $staffH->id)->count())->toBe(2)
        ->and(AttendanceEnrolMap::acrossCompanies()->where('pin', '2056')->value('employee_id'))->toBe($staffH->id);
});

it('imports the Fingerspot daily report, mapping PINs by NIP', function () {
    $alaina = Employee::factory()->forCompany($this->company)->create(['employee_number' => '141276211004006']);

    $this->actingAs($this->hr)
        ->post(route('attendance.imports.store'), ['file' => fingerspotDailyReport()])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $import = AttendanceImport::first();

    // The counts still add up scan by scan: 12 read, 2 filed for the one mapped person, 10 waiting for a mapping.
    expect($import->device_sn)->toBe('66208025470046')
        ->and($import->rows_total)->toBe(12)
        ->and($import->rejected)->toBe(0)
        ->and($import->auto_mapped)->toBe(1)
        ->and($import->accepted)->toBe(2)
        ->and($import->unmapped)->toBe(10)
        ->and(AttendancePunch::acrossCompanies()->where('employee_id', $alaina->id)->count())->toBe(2);
});

it('does nothing the second time the same daily report is loaded', function () {
    Employee::factory()->forCompany($this->company)->create(['employee_number' => '141276211004006']);

    $this->actingAs($this->hr)->post(route('attendance.imports.store'), ['file' => fingerspotDailyReport()]);
    $this->actingAs($this->hr)->post(route('attendance.imports.store'), ['file' => fingerspotDailyReport()]);

    $second = AttendanceImport::latest('id')->first();

    expect($second->accepted)->toBe(0)
        ->and($second->duplicates)->toBe(12)
        ->and(AttendancePunch::acrossCompanies()->count())->toBe(2);
});

it('leaves a PIN alone when it already has a mapping or its NIP is ambiguous', function () {
    Employee::factory()->forCompany($this->company)->create(['employee_number' => 'NIP-1']);
    Employee::factory()->forCompany($this->company)->create(['employee_number' => 'NIP-2']);
    Employee::factory()->forCompany(Company::factory()->create())->create(['employee_number' => 'NIP-2']);
    mapPin('1042', $this->employee, 'SN1', null, '2026-09-30');

    $this->actingAs($this->hr)->post(route('attendance.imports.store'), [
        'device_sn' => 'SN1',
        'file' => csv('pin,nip,scan_at', '1042,NIP-1,2026-10-05 07:55:10', '7,NIP-2,2026-10-05 07:56:00'),
    ]);

    expect(AttendanceImport::first()->auto_mapped)->toBe(0)
        ->and(AttendanceEnrolMap::acrossCompanies()->count())->toBe(1)
        ->and(AttendanceUnmappedPunch::count())->toBe(2);
});

it('asks for the serial when the file does not carry one', function () {
    $this->actingAs($this->hr)
        ->post(route('attendance.imports.store'), ['file' => csv('pin,scan_at', '1042,2026-10-05 07:55:10')])
        ->assertSessionHasErrors('file');

    expect(AttendanceImport::count())->toBe(0);
});

it('accepts a spreadsheet and refuses other kinds of file', function () {
    mapPin('1042', $this->employee);

    $this->actingAs($this->hr)->post(route('attendance.imports.store'), [
        'device_sn' => 'SN1', 'file' => xlsx([['PIN', 'Tanggal scan'], ['1042', '05-10-2026 07:55:10']]),
    ])->assertSessionHasNoErrors();

    $this->actingAs($this->hr)->post(route('attendance.imports.store'), [
        'device_sn' => 'SN1', 'file' => UploadedFile::fake()->create('log.pdf', 10),
    ])->assertSessionHasErrors('file');

    expect(AttendancePunch::acrossCompanies()->count())->toBe(1);
});

it('files punches for a mapped PIN under that employee and company', function () {
    mapPin('1042', $this->employee);

    $this->actingAs($this->hr)
        ->post(route('attendance.imports.store'), [
            'device_sn' => 'SN1',
            'file' => csv('pin,scan_at', '1042,2026-10-05 07:55:10', '1042,2026-10-05 17:02:00'),
        ])
        ->assertRedirect();

    expect(AttendancePunch::acrossCompanies()->count())->toBe(2)
        ->and(AttendancePunch::acrossCompanies()->first()->employee_id)->toBe($this->employee->id)
        ->and(AttendancePunch::acrossCompanies()->first()->company_id)->toBe($this->company->id);

    $import = AttendanceImport::first();

    expect($import->accepted)->toBe(2)
        ->and($import->duplicates)->toBe(0)
        ->and($import->user_id)->toBe($this->hr->id);
});

it('does nothing the second time the same file is loaded', function () {
    mapPin('1042', $this->employee);
    $file = fn () => csv('pin,scan_at', '1042,2026-10-05 07:55:10', '1042,2026-10-05 17:02:00');

    $this->actingAs($this->hr)->post(route('attendance.imports.store'), ['device_sn' => 'SN1', 'file' => $file()]);
    $this->actingAs($this->hr)->post(route('attendance.imports.store'), ['device_sn' => 'SN1', 'file' => $file()]);

    expect(AttendancePunch::acrossCompanies()->count())->toBe(2);

    $second = AttendanceImport::latest('id')->first();

    expect($second->accepted)->toBe(0)->and($second->duplicates)->toBe(2);
});

it('adds only the new rows when files overlap, and ignores a repeat inside one file', function () {
    mapPin('1042', $this->employee);

    $this->actingAs($this->hr)->post(route('attendance.imports.store'), [
        'device_sn' => 'SN1', 'file' => csv('pin,scan_at', '1042,2026-10-05 07:55:10'),
    ]);
    $this->actingAs($this->hr)->post(route('attendance.imports.store'), [
        'device_sn' => 'SN1',
        'file' => csv('pin,scan_at', '1042,2026-10-05 07:55:10', '1042,2026-10-06 08:00:00', '1042,2026-10-06 08:00:00'),
    ]);

    $second = AttendanceImport::latest('id')->first();

    expect(AttendancePunch::acrossCompanies()->count())->toBe(2)
        ->and($second->accepted)->toBe(1)
        ->and($second->duplicates)->toBe(2);
});

it('holds punches for an unmapped PIN instead of dropping them', function () {
    $this->actingAs($this->hr)->post(route('attendance.imports.store'), [
        'device_sn' => 'SN1', 'file' => csv('pin,scan_at', '9999,2026-10-05 07:55:10'),
    ]);

    expect(AttendancePunch::acrossCompanies()->count())->toBe(0)
        ->and(AttendanceUnmappedPunch::count())->toBe(1)
        ->and(AttendanceImport::first()->unmapped)->toBe(1);
});

it('releases waiting punches into attendance when the PIN is mapped', function () {
    $this->actingAs($this->hr)->post(route('attendance.imports.store'), [
        'device_sn' => 'SN1', 'file' => csv('pin,scan_at', '1042,2026-10-05 07:55:10', '1042,2026-10-06 07:50:00'),
    ]);

    expect(AttendanceUnmappedPunch::count())->toBe(2);

    $this->actingAs($this->hr)->post(route('attendance.enrol-maps.store'), [
        'device_sn' => 'SN1', 'pin' => '1042', 'employee_id' => $this->employee->id,
    ])->assertRedirect();

    expect(AttendanceUnmappedPunch::count())->toBe(0)
        ->and(AttendancePunch::acrossCompanies()->where('employee_id', $this->employee->id)->count())->toBe(2);
});

it('uses the mapping in force on the day of the scan', function () {
    $newHire = Employee::factory()->forCompany($this->company)->create();
    mapPin('1042', $this->employee, 'SN1', null, '2026-10-05');
    mapPin('1042', $newHire, 'SN1', '2026-10-06', null);

    $this->actingAs($this->hr)->post(route('attendance.imports.store'), [
        'device_sn' => 'SN1',
        'file' => csv('pin,scan_at', '1042,2026-10-05 07:55:10', '1042,2026-10-06 07:50:00'),
    ]);

    expect(AttendancePunch::acrossCompanies()->where('employee_id', $this->employee->id)->count())->toBe(1)
        ->and(AttendancePunch::acrossCompanies()->where('employee_id', $newHire->id)->count())->toBe(1);
});

it('judges the mapping date in the scanner\'s timezone, not UTC', function () {
    // 23:50 Jakarta on the 5th is 16:50 UTC on the 5th; 06:30 Jakarta on the 6th is 23:30 UTC on
    // the 5th — still the 6th where the scanner is, so it must belong to the new mapping.
    $newHire = Employee::factory()->forCompany($this->company)->create();
    mapPin('1042', $this->employee, 'SN1', null, '2026-10-05');
    mapPin('1042', $newHire, 'SN1', '2026-10-06', null);

    $this->actingAs($this->hr)->post(route('attendance.imports.store'), [
        'device_sn' => 'SN1', 'file' => csv('pin,scan_at', '1042,2026-10-06 06:30:00'),
    ]);

    expect(AttendancePunch::acrossCompanies()->first()->employee_id)->toBe($newHire->id);
});

it('refuses overlapping mappings for one PIN', function () {
    mapPin('1042', $this->employee);

    expect(fn () => mapPin('1042', Employee::factory()->forCompany($this->company)->create()))
        ->toThrow(ValidationException::class);
});

it('uses the serial number in the file when there is one', function () {
    mapPin('1042', $this->employee, 'FILE-SN');

    $this->actingAs($this->hr)->post(route('attendance.imports.store'), [
        'device_sn' => 'TYPED-SN', 'file' => csv('pin,scan_at,sn', '1042,2026-10-05 07:55:10,FILE-SN'),
    ]);

    expect(AttendancePunch::acrossCompanies()->first()->device_sn)->toBe('FILE-SN');
});

it('rejects an unreadable file with a message, and records no import', function () {
    $this->actingAs($this->hr)
        ->from(route('attendance.device'))
        ->post(route('attendance.imports.store'), ['device_sn' => 'SN1', 'file' => csv('nama,alamat', 'a,b')])
        ->assertSessionHasErrors('file');

    expect(AttendanceImport::count())->toBe(0);
});

it('requires a file', function () {
    $this->actingAs($this->hr)->post(route('attendance.imports.store'), [])
        ->assertSessionHasErrors('file')
        ->assertSessionDoesntHaveErrors('device_sn');
});

// --- Access ---

it('is open to a parent-wide HR admin only', function () {
    $this->actingAs($this->hr)->get(route('attendance.device'))->assertOk();

    $this->actingAs(attendanceUser(Role::HrAdmin, $this->company))->get(route('attendance.device'))->assertForbidden();
    $this->actingAs(attendanceUser(Role::Supervisor))->get(route('attendance.device'))->assertForbidden();
    $this->actingAs(attendanceUser(Role::Employee, $this->company))->get(route('attendance.device'))->assertForbidden();
});

it('refuses uploads and mappings to anyone else', function () {
    $pinned = attendanceUser(Role::HrAdmin, $this->company);

    $this->actingAs($pinned)->post(route('attendance.imports.store'), [
        'device_sn' => 'SN1', 'file' => csv('pin,scan_at', '1,2026-10-05 07:00:00'),
    ])->assertForbidden();

    $this->actingAs($pinned)->post(route('attendance.enrol-maps.store'), [
        'device_sn' => 'SN1', 'pin' => '1', 'employee_id' => $this->employee->id,
    ])->assertForbidden();

    expect(AttendanceImport::count())->toBe(0);
});

it('removes a mapping but keeps the punches already filed', function () {
    mapPin('1042', $this->employee);
    $this->actingAs($this->hr)->post(route('attendance.imports.store'), [
        'device_sn' => 'SN1', 'file' => csv('pin,scan_at', '1042,2026-10-05 07:55:10'),
    ]);

    $map = AttendanceEnrolMap::acrossCompanies()->first();

    $this->actingAs($this->hr)->delete(route('attendance.enrol-maps.destroy', $map->id))->assertRedirect();

    expect(AttendanceEnrolMap::acrossCompanies()->count())->toBe(0)
        ->and(AttendancePunch::acrossCompanies()->count())->toBe(1);
});

it('lists waiting IDs with a suggestion where the PIN equals one employee number', function () {
    $this->actingAs($this->hr)->post(route('attendance.imports.store'), [
        'device_sn' => 'SN1', 'file' => csv('pin,scan_at', '1042,2026-10-05 07:55:10', '5555,2026-10-05 07:56:00'),
    ]);

    $this->actingAs($this->hr)->get(route('attendance.device'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('waiting', 2)
            ->where('waiting.0.pin', '1042')
            ->where('waiting.0.suggestedEmployeeId', $this->employee->id)
            ->where('waiting.1.pin', '5555')
            ->where('waiting.1.suggestedEmployeeId', null)
            ->where('status.unmapped', 2));
});

it('offers a company-pinned user\'s attendance control only to those who can use it', function () {
    $this->actingAs($this->hr)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canManageAttendance', true));

    $this->actingAs(attendanceUser(Role::HrAdmin, $this->company))->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canManageAttendance', false));
});

// --- How current the data is ---

it('reports data as current when it runs through the previous working day', function (string $now, string $lastScan, string $expected) {
    mapPin('1042', $this->employee);
    app(IngestPunches::class)->handle([[
        'sn' => 'SN1', 'pin' => '1042', 'scan_at' => CarbonImmutable::parse($lastScan, 'Asia/Jakarta')->utc(),
        'verify_mode' => null, 'inout_mode' => null,
    ]], 'manual-file');

    $status = app(CaptureStatus::class)->resolve(CarbonImmutable::parse($now, 'Asia/Jakarta'));

    expect($status['state'])->toBe($expected);
})->with([
    'Tuesday morning, Monday captured' => ['2026-10-13 09:00:00', '2026-10-12 17:00:00', 'current'],
    'Tuesday morning, only Friday captured' => ['2026-10-13 09:00:00', '2026-10-09 17:00:00', 'late'],
    'Monday morning, Friday captured' => ['2026-10-12 09:00:00', '2026-10-09 17:00:00', 'current'],
    'Saturday, Friday captured' => ['2026-10-10 09:00:00', '2026-10-09 17:00:00', 'current'],
    'Monday morning, Thursday captured' => ['2026-10-12 09:00:00', '2026-10-08 17:00:00', 'late'],
]);

it('does not call the data late over a holiday that covers the whole group', function () {
    mapPin('1042', $this->employee);
    app(IngestPunches::class)->handle([[
        'sn' => 'SN1', 'pin' => '1042', 'scan_at' => CarbonImmutable::parse('2026-10-09 17:00:00', 'Asia/Jakarta')->utc(),
        'verify_mode' => null, 'inout_mode' => null,
    ]], 'manual-file');
    $tuesday = CarbonImmutable::parse('2026-10-13 09:00:00', 'Asia/Jakarta');

    // Monday the 12th is a holiday for everyone: nobody scanned, and nobody was meant to.
    expect(app(CaptureStatus::class)->resolve($tuesday)['state'])->toBe('late');

    CalendarEvent::create(['title' => 'Libur', 'kind' => 'holiday', 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-12']);

    $status = app(CaptureStatus::class)->resolve($tuesday);

    expect($status['state'])->toBe('current')
        ->and($status['expectedThrough'])->toBe('2026-10-09');
});

it('keeps expecting data over a holiday that only covers one company or department', function () {
    mapPin('1042', $this->employee);
    app(IngestPunches::class)->handle([[
        'sn' => 'SN1', 'pin' => '1042', 'scan_at' => CarbonImmutable::parse('2026-10-09 17:00:00', 'Asia/Jakarta')->utc(),
        'verify_mode' => null, 'inout_mode' => null,
    ]], 'manual-file');

    CalendarEvent::create(['company_id' => $this->company->id, 'title' => 'Libur perusahaan', 'kind' => 'holiday', 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-12']);

    $status = app(CaptureStatus::class)->resolve(CarbonImmutable::parse('2026-10-13 09:00:00', 'Asia/Jakarta'));

    expect($status['state'])->toBe('late')
        ->and($status['expectedThrough'])->toBe('2026-10-12');
});

it('keeps expecting data over a holiday that only covers one Cabang', function () {
    mapPin('1042', $this->employee);
    app(IngestPunches::class)->handle([[
        'sn' => 'SN1', 'pin' => '1042', 'scan_at' => CarbonImmutable::parse('2026-10-09 17:00:00', 'Asia/Jakarta')->utc(),
        'verify_mode' => null, 'inout_mode' => null,
    ]], 'manual-file');

    CalendarEvent::create(['title' => 'Libur cabang', 'kind' => 'holiday', 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-12', 'branch_id' => $this->employee->branch_id]);

    $status = app(CaptureStatus::class)->resolve(CarbonImmutable::parse('2026-10-13 09:00:00', 'Asia/Jakarta'));

    expect($status['state'])->toBe('late')
        ->and($status['expectedThrough'])->toBe('2026-10-12');
});

it('reports never when nothing has been loaded', function () {
    expect(app(CaptureStatus::class)->resolve()['state'])->toBe('never');
});

it('shows the capture reminder on the dashboard only to people who load the log', function () {
    $this->actingAs($this->hr)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('attendanceStatus.state', 'never')
            ->has('attendanceStatus.expectedThrough'));

    $this->actingAs(attendanceUser(Role::Supervisor))->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('attendanceStatus', null));
});

it('loads the bundled sample file the way the page describes', function () {
    $this->actingAs($this->hr)->post(route('attendance.imports.store'), [
        'device_sn' => 'DEMO-SN',
        'file' => new UploadedFile(base_path('tests/Fixtures/scanlog-sample.csv'), 'scanlog-sample.csv', 'text/csv', null, true),
    ])->assertRedirect();

    $import = AttendanceImport::first();

    expect($import->rows_total)->toBe(61)
        ->and($import->rejected)->toBe(1)
        ->and($import->unmapped)->toBe(60)
        ->and(AttendanceUnmappedPunch::distinct('pin')->count('pin'))->toBe(6);
});
