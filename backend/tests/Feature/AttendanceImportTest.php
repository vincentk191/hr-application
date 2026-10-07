<?php

use App\Actions\Attendance\IngestPunches;
use App\Actions\Attendance\MapEnrolId;
use App\Enums\Role;
use App\Models\AttendanceEnrolMap;
use App\Models\AttendanceImport;
use App\Models\AttendancePunch;
use App\Models\AttendanceUnmappedPunch;
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

// --- Importing ---

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

it('requires a serial number and a file', function () {
    $this->actingAs($this->hr)->post(route('attendance.imports.store'), [])
        ->assertSessionHasErrors(['device_sn', 'file']);
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
