<?php

use App\Enums\AttendanceSource;
use App\Enums\Role;
use App\Models\AttendanceEnrolMap;
use App\Models\AttendancePunch;
use App\Models\AttendanceRekap;
use App\Models\AttendanceRekapRow;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Position;
use App\Models\RequestType;
use App\Models\User;
use App\Support\CsvDownload;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * The attendance recap, the CSV exports and the team / leave-type filters (EMP-ATT-003,
 * ATT-CAL-004, ATT-CAL-005).
 *
 * October 2026: the 5th is a Monday.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config([
        'attendance.device_timezone' => 'Asia/Jakarta',
        'attendance.schedule' => ['start' => '08:00', 'end' => '17:00', 'late_grace_minutes' => 10],
    ]);
    $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00', 'Asia/Jakarta'));
    $this->company = Company::factory()->create();
    $this->hr = recapUser(Role::HrAdmin, $this->company);
    $this->ops = Department::factory()->forCompany($this->company)->create(['name' => 'Operasional']);
    $this->fin = Department::factory()->forCompany($this->company)->create(['name' => 'Keuangan']);
    $this->budi = recapEmployee($this->company, 'Budi', $this->ops);
    $this->sari = recapEmployee($this->company, 'Sari', $this->ops);
    $this->dewi = recapEmployee($this->company, 'Dewi', $this->fin);
});

function recapUser(Role $role, ?Company $company = null): User
{
    $user = User::factory()->create(['company_id' => $company?->id]);
    $user->assignRole($role->value);

    return $user;
}

function recapEmployee(Company $company, string $name, ?Department $department = null, ?Employee $manager = null): Employee
{
    $employee = Employee::factory()->forCompany($company)->create([
        'full_name' => $name, 'joined_on' => '2020-01-01', 'department_id' => $department?->id, 'manager_id' => $manager?->id,
    ]);
    AttendanceEnrolMap::create(['company_id' => $company->id, 'employee_id' => $employee->id, 'device_sn' => 'SN1', 'pin' => 'P'.$employee->id]);

    return $employee;
}

function recapScan(Employee $employee, string $local): void
{
    AttendancePunch::create([
        'company_id' => $employee->company_id, 'employee_id' => $employee->id, 'device_sn' => 'SN1', 'pin' => 'P'.$employee->id,
        'scan_at' => CarbonImmutable::parse($local, 'Asia/Jakarta')->utc(), 'source' => 'manual-file',
    ]);
}

/**
 * Two days of data: the 5th (everyone full except Sari, late) and the 6th (Budi only, leaving early).
 */
function recapData(): void
{
    foreach (['budi', 'sari', 'dewi'] as $who) {
        $employee = test()->{$who};
        recapScan($employee, '2026-10-05 '.($who === 'sari' ? '09:00' : '08:00').':00');
        recapScan($employee, '2026-10-05 17:00:00');
    }

    recapScan(test()->budi, '2026-10-06 08:00:00');
    recapScan(test()->budi, '2026-10-06 15:00:00');
}

// --- The recap ---

it('totals each employee, each department and everyone for the period', function () {
    recapData();

    $props = $this->actingAs($this->hr)->get('/attendance/recap?from=2026-10-05&to=2026-10-06')->assertOk()->viewData('page')['props'];
    $rows = collect($props['rows'])->keyBy('name');

    expect($rows['Budi']['present'])->toBe(1)
        ->and($rows['Budi']['earlyLeave'])->toBe(1)
        ->and($rows['Budi']['minutes'])->toBe(540 + 420)
        ->and($rows['Sari']['late'])->toBe(1)
        ->and($rows['Sari']['absent'])->toBe(1)
        ->and($rows['Dewi']['present'])->toBe(1)
        ->and($rows['Dewi']['absent'])->toBe(1);

    $departments = collect($props['departments'])->keyBy('name');

    expect($departments['Operasional']['employees'])->toBe(2)
        ->and($departments['Operasional']['present'])->toBe(1)
        ->and($departments['Operasional']['late'])->toBe(1)
        ->and($departments['Operasional']['absent'])->toBe(1)
        ->and($departments['Keuangan']['present'])->toBe(1);

    expect($props['total']['employees'])->toBe(3)
        ->and($props['total']['attended'])->toBe(4)
        ->and($props['total']['absent'])->toBe(2);
});

it('agrees with the calendar for the same days', function () {
    recapData();

    $recap = $this->actingAs($this->hr)->get('/attendance/recap?from=2026-10-05&to=2026-10-06')->viewData('page')['props']['total'];
    $calendar = $this->actingAs($this->hr)->get('/attendance/calendar?view=week&date=2026-10-05&departments[]='.urlencode($this->ops->name).'&departments[]='.urlencode($this->fin->name))
        ->viewData('page')['props']['summary']['counts'];

    // The calendar's week also counts 7-9 October (no data) and the weekend; compare what is judged.
    expect($recap['present'])->toBe($calendar['present'])
        ->and($recap['late'])->toBe($calendar['late'])
        ->and($recap['earlyLeave'])->toBe($calendar['early_leave'])
        ->and($recap['absent'])->toBe($calendar['absent']);
});

it('narrows by department, search and period', function () {
    recapData();
    $base = '/attendance/recap?from=2026-10-05&to=2026-10-06';

    $names = fn (string $query) => collect($this->actingAs($this->hr)->get($base.$query)->viewData('page')['props']['rows'])->pluck('name')->all();

    expect($names('&departments[]='.urlencode($this->fin->name)))->toBe(['Dewi'])
        ->and($names('&search=Sar'))->toBe(['Sari'])
        ->and(collect($this->actingAs($this->hr)->get('/attendance/recap?from=2026-10-05&to=2026-10-05')->viewData('page')['props']['rows'])->sum('absent'))->toBe(0);
});

it('keeps the period within a year', function () {
    $filters = $this->actingAs($this->hr)->get('/attendance/recap?from=2020-01-01&to=2026-10-06')->viewData('page')['props']['filters'];

    expect($filters['to'])->toBe('2026-10-06')
        ->and($filters['from'])->toBe('2025-10-06');
});

it('is closed to roles without the permission and scoped to the company', function () {
    $this->actingAs(recapUser(Role::Supervisor, $this->company))->get('/attendance/recap')->assertForbidden();

    $other = Company::factory()->create();
    recapEmployee($other, 'Orang Lain');

    $names = collect($this->actingAs($this->hr)->get('/attendance/recap?from=2026-10-05&to=2026-10-06')->viewData('page')['props']['rows'])->pluck('name')->all();

    expect($names)->not->toContain('Orang Lain');
});

it('totals each Cabang and narrows every attendance screen to the ones chosen', function () {
    $surabaya = Branch::factory()->forCompany($this->company)->create(['name' => 'Surabaya']);
    $this->dewi->update(['branch_id' => $surabaya->id]);
    recapData();

    $props = $this->actingAs($this->hr)->get('/attendance/recap?from=2026-10-05&to=2026-10-06')->assertOk()->viewData('page')['props'];
    $branches = collect($props['branches'])->keyBy('name');

    expect($branches['Pusat']['employees'])->toBe(2)
        ->and($branches['Pusat']['late'])->toBe(1)
        ->and($branches['Surabaya']['employees'])->toBe(1)
        ->and($branches['Surabaya']['present'])->toBe(1)
        ->and($props['options']['branches'])->toBe(['Pusat', 'Surabaya']);

    $filtered = $this->actingAs($this->hr)->get('/attendance/recap?from=2026-10-05&to=2026-10-06&branches[]=Surabaya')->viewData('page')['props'];

    expect(collect($filtered['rows'])->pluck('name')->all())->toBe(['Dewi'])
        ->and($filtered['total']['employees'])->toBe(1);

    $calendar = $this->actingAs($this->hr)->get('/attendance/calendar?view=week&date=2026-10-05&branches[]=Surabaya')->viewData('page')['props'];
    $issues = $this->actingAs($this->hr)->get('/attendance/issues?from=2026-10-05&to=2026-10-06&state=all&branches[]=Surabaya')->viewData('page')['props'];

    expect(collect($calendar['rows'])->pluck('name')->all())->toBe(['Dewi'])
        ->and(collect($calendar['rows'])->pluck('branch')->unique()->all())->toBe(['Surabaya'])
        ->and(collect($issues['issues'])->pluck('name')->unique()->all())->toBe(['Dewi']);
});

// --- Exports ---

it('exports the recap as CSV with department subtotals and a total', function () {
    recapData();

    $response = $this->actingAs($this->hr)->get('/attendance/recap?from=2026-10-05&to=2026-10-06&export=csv');
    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $csv = $response->streamedContent();

    expect($csv)->toStartWith("\xEF\xBB\xBF")
        ->and($csv)->toContain('Rekap absensi')
        ->and($csv)->toContain('Karyawan,ID,Cabang,Departemen,Hadir,Terlambat')
        ->and($csv)->toContain('Budi,')
        ->and($csv)->toContain('Per cabang')
        ->and($csv)->toContain('Pusat,"3 karyawan"')
        ->and($csv)->toContain('Per departemen')
        ->and($csv)->toContain('Operasional,"2 karyawan"')
        ->and($csv)->toContain('Total,"3 karyawan"')
        // Budi: 9h on the 5th and 7h on the 6th.
        ->and($csv)->toContain('16.00');
});

it('exports the calendar as the grid that was on screen, with a letter and times per day', function () {
    recapData();

    $csv = $this->actingAs($this->hr)->get('/attendance/calendar/export?view=week&date=2026-10-05')->assertOk()->streamedContent();

    expect($csv)->toContain('Kalender')
        ->and($csv)->toContain('2026-10-05')
        ->and($csv)->toContain('H 08:00-17:00')   // Budi on time
        ->and($csv)->toContain('T 09:00-17:00')   // Sari late
        ->and($csv)->toContain('P 08:00-15:00')   // Budi left early
        ->and($csv)->toContain('A')                // someone absent on the 6th
        ->and($csv)->toContain('Keterangan');
});

it('exports the same filtered rows as the page shows', function () {
    recapData();

    $csv = $this->actingAs($this->hr)->get('/attendance/calendar/export?view=week&date=2026-10-05&departments[]='.urlencode($this->fin->name))
        ->streamedContent();

    expect($csv)->toContain('Dewi')->not->toContain('Budi')->not->toContain('Sari');
});

it('exports the year view as months', function () {
    recapData();

    $csv = $this->actingAs($this->hr)->get('/attendance/calendar/export?view=year&date=2026-10-05')->streamedContent();

    expect($csv)->toContain('Bulan,Hadir')->toContain('Oktober');
});

it('refuses an export to roles without the permission', function () {
    $this->actingAs(recapUser(Role::Employee, $this->company))->get('/attendance/calendar/export')->assertForbidden();
});

it('defuses spreadsheet formulas in exported text', function () {
    expect(CsvDownload::safe('=SUM(A1)'))->toBe("'=SUM(A1)")
        ->and(CsvDownload::safe('+62812'))->toBe("'+62812")
        ->and(CsvDownload::safe('@cmd'))->toBe("'@cmd")
        ->and(CsvDownload::safe('-5'))->toBe("'-5")
        ->and(CsvDownload::safe('Budi'))->toBe('Budi')
        ->and(CsvDownload::safe(12))->toBe('12')
        ->and(CsvDownload::safe(null))->toBe('');
});

// --- Team and leave-type filters ---

it('filters the calendar to a supervisor\'s team, at any depth', function () {
    $lead = recapEmployee($this->company, 'Atasan');
    $direct = recapEmployee($this->company, 'Anak Buah', null, $lead);
    $deep = recapEmployee($this->company, 'Cucu Buah', null, $direct);
    recapEmployee($this->company, 'Orang Lain Tim');
    recapScan($lead, '2026-10-05 08:00:00');

    $names = collect($this->actingAs($this->hr)->get('/attendance/calendar?view=day&date=2026-10-05&team='.$lead->id)->viewData('page')['props']['rows'])
        ->pluck('name')->sort()->values()->all();

    expect($names)->toBe(['Anak Buah', 'Cucu Buah']);

    $options = $this->actingAs($this->hr)->get('/attendance/calendar')->viewData('page')['props']['options']['teams'];

    expect(collect($options)->pluck('full_name')->sort()->values()->all())->toBe(['Anak Buah', 'Atasan']);
});

it('filters the calendar to people with approved leave of a kind', function () {
    recapScan($this->dewi, '2026-10-05 08:00:00');

    $approve = function (Employee $employee, string $type) {
        test()->actingAs(test()->hr)->post(route('leave-requests.store'), [
            'employee_id' => $employee->id, 'leave_type' => $type, 'start_on' => '2026-10-06', 'end_on' => '2026-10-06', 'reason' => 'Perlu',
            // Sakit needs proof.
            'attachments' => $type === 'sick' ? [UploadedFile::fake()->create('surat.pdf', 10, 'application/pdf')] : [],
        ])->assertRedirect();
        test()->actingAs(test()->hr)->put(route('leave-requests.decision', LeaveRequest::latest('id')->first()), ['decision' => 'approve'])->assertRedirect();
    };

    $approve($this->budi, 'sick');
    $approve($this->sari, 'annual');

    $names = fn (string $type) => collect($this->actingAs($this->hr)->get("/attendance/calendar?view=week&date=2026-10-05&leave_type={$type}")->viewData('page')['props']['rows'])
        ->pluck('name')->all();

    expect($names('sick'))->toBe(['Budi'])
        ->and($names('annual'))->toBe(['Sari'])
        ->and($names('bogus'))->toHaveCount(3);
});

it('offers the team and leave-type options on the calendar', function () {
    $options = $this->actingAs($this->hr)->get('/attendance/calendar')->assertInertia(fn (Assert $page) => $page->has('options.leaveTypes')->etc())
        ->viewData('page')['props']['options'];

    expect(collect($options['leaveTypes'])->pluck('value')->all())->toContain('sick', 'annual');
});

it('totals the range per week when it is a month or less, and per month when longer', function () {
    $weeks = $this->actingAs($this->hr)->get('/attendance/recap?from=2026-10-01&to=2026-10-15')->viewData('page')['props']['periods'];

    expect(collect($weeks)->pluck('from')->all())->toBe(['2026-10-01', '2026-10-05', '2026-10-12'])
        ->and($weeks[0]['to'])->toBe('2026-10-04')
        ->and($weeks[1]['label'])->toBe('5 Okt – 11 Okt');

    $months = $this->actingAs($this->hr)->get('/attendance/recap?from=2026-09-15&to=2026-11-10')->viewData('page')['props']['periods'];

    expect(collect($months)->pluck('label')->all())->toBe(['September 2026', 'Oktober 2026', 'November 2026'])
        ->and($months[0]['from'])->toBe('2026-09-15')
        ->and($months[2]['to'])->toBe('2026-11-10');
});

it('makes the period totals add up to the grand total, and exports them', function () {
    recapData();

    $props = $this->actingAs($this->hr)->get('/attendance/recap?from=2026-10-01&to=2026-10-31')->viewData('page')['props'];

    foreach (['present', 'late', 'absent', 'leave', 'minutes'] as $key) {
        expect(collect($props['periods'])->sum($key))->toBe($props['total'][$key]);
    }

    $csv = $this->actingAs($this->hr)->get('/attendance/recap?from=2026-10-01&to=2026-10-31&export=csv')->streamedContent();

    expect($csv)->toContain('Per periode')->toContain('5 Okt – 11 Okt');
});

// --- Alpa, Izin, Sakit, Telat, Zona and Potongan ---

/**
 * Approved leave of a kind for one working day, filed and approved by HR.
 */
function recapLeave(Employee $employee, string $type, string $day): void
{
    test()->actingAs(test()->hr)->post(route('leave-requests.store'), [
        'employee_id' => $employee->id, 'leave_type' => $type, 'start_on' => $day, 'end_on' => $day, 'reason' => 'Perlu',
        'attachments' => $type === 'sick' ? [UploadedFile::fake()->create('surat.pdf', 10, 'application/pdf')] : [],
    ])->assertSessionHasNoErrors();

    test()->actingAs(test()->hr)->put(route('leave-requests.decision', LeaveRequest::latest('id')->firstOrFail()), ['decision' => 'approve'])->assertRedirect();
}

it('counts Alpa and Telat from the days, and prices them by the employee\'s Zona', function () {
    recapData();
    $this->dewi->update(['zona' => 'merah']);

    $rows = collect($this->actingAs($this->hr)->get('/attendance/recap?from=2026-10-05&to=2026-10-06')->viewData('page')['props']['rows'])->keyBy('name');

    // Sari: late on the 5th (half a Hijau day), absent on the 6th (a whole one). Dewi: absent on the 6th, Merah.
    expect($rows['Sari'])->toMatchArray(['alpa' => 1, 'telat' => 1, 'izin' => 0, 'sakit' => 0, 'zona' => 'Hijau', 'source' => 'scanner', 'potongan' => 75000])
        ->and($rows['Dewi'])->toMatchArray(['alpa' => 1, 'zona' => 'Merah', 'potongan' => 100000])
        ->and($rows['Budi'])->toMatchArray(['alpa' => 0, 'telat' => 0, 'potongan' => 0]);

    $total = $this->actingAs($this->hr)->get('/attendance/recap?from=2026-10-05&to=2026-10-06')->viewData('page')['props']['total'];

    expect($total['potongan'])->toBe(175000)->and($total['alpa'])->toBe(2);
});

it('splits approved leave into Izin, Sakit and other leave by the kind of request', function () {
    recapData();
    RequestType::create(['label' => 'Izin pribadi', 'absence_kind' => 'izin', 'is_active' => true]);
    RequestType::where('code', 'sick')->update(['max_back_days' => 30]);

    recapLeave($this->budi, 'izin-pribadi', '2026-10-07');
    recapLeave($this->sari, 'sick', '2026-10-08');
    recapLeave($this->dewi, 'annual', '2026-10-09');

    $rows = collect($this->actingAs($this->hr)->get('/attendance/recap?from=2026-10-05&to=2026-10-09')->viewData('page')['props']['rows'])->keyBy('name');

    // Izin costs a whole day, Sakit nothing, and annual leave is neither.
    expect($rows['Budi'])->toMatchArray(['izin' => 1, 'sakit' => 0, 'otherLeave' => 0, 'leave' => 1, 'potongan' => 50000])
        ->and($rows['Sari'])->toMatchArray(['izin' => 0, 'sakit' => 1, 'otherLeave' => 0, 'alpa' => 1, 'telat' => 1, 'potongan' => 75000])
        ->and($rows['Dewi'])->toMatchArray(['izin' => 0, 'sakit' => 0, 'otherLeave' => 1, 'leave' => 1, 'alpa' => 1]);
});

it('exempts a Supervisor from the deduction', function () {
    recapData();
    $supervisor = Position::factory()->forDepartment($this->fin)->create(['name' => 'Supervisor']);
    $this->dewi->update(['position_id' => $supervisor->id]);

    $dewi = collect($this->actingAs($this->hr)->get('/attendance/recap?from=2026-10-05&to=2026-10-06')->viewData('page')['props']['rows'])->firstWhere('name', 'Dewi');

    expect($dewi['alpa'])->toBe(1)->and($dewi['potongan'])->toBe(0);
});

it('reads a Cabang that sends a rekapitulasi from its confirmed files inside the range', function () {
    recapData();
    $branch = Branch::factory()->forCompany($this->company)->create(['name' => 'Tangerang', 'attendance_source' => AttendanceSource::Rekap]);
    $rina = Employee::factory()->forCompany($this->company)->create(['full_name' => 'Rina', 'branch_id' => $branch->id, 'joined_on' => '2020-01-01', 'zona' => 'merah']);

    $rekap = fn (string $status, string $from, string $to) => AttendanceRekap::create([
        'company_id' => $this->company->id, 'branch_id' => $branch->id, 'status' => $status, 'period_start' => $from, 'period_end' => $to,
    ]);
    $line = fn (AttendanceRekap $file, array $counts) => AttendanceRekapRow::create([
        'attendance_rekap_id' => $file->id, 'employee_id' => $rina->id, 'row_number' => 1, 'name' => 'RINA', 'match_status' => 'matched', ...$counts,
    ]);

    $line($rekap('confirmed', '2026-09-21', '2026-10-20'), ['alpa' => 1, 'izin' => 2, 'sakit' => 3, 'telat' => 4, 'computed_potongan' => 500000]);
    // A draft counts for nothing.
    $line($rekap('draft', '2026-09-21', '2026-10-20'), ['alpa' => 9, 'computed_potongan' => 900000]);

    $props = $this->actingAs($this->hr)->get('/attendance/recap?from=2026-09-01&to=2026-10-31')->viewData('page')['props'];
    $row = collect($props['rows'])->firstWhere('name', 'Rina');

    expect($row)->toMatchArray([
        'source' => 'rekap', 'rekapMissing' => false, 'zona' => 'Merah',
        'alpa' => 1, 'izin' => 2, 'sakit' => 3, 'telat' => 4, 'potongan' => 500000,
        // Nothing is counted from days for a Cabang without a scanner.
        'present' => 0, 'noData' => 0,
    ])
        ->and($props['rekapNotes'])->toBe([])
        ->and($props['total']['potongan'])->toBe(500000 + 125000 + 0)
        ->and($props['branches'])->toHaveCount(2);

    // A shorter range cuts the file's period in two: it is not counted, and the screen is told why.
    $cut = $this->actingAs($this->hr)->get('/attendance/recap?from=2026-10-05&to=2026-10-06')->viewData('page')['props'];
    $rina = collect($cut['rows'])->firstWhere('name', 'Rina');

    expect($rina)->toMatchArray(['rekapMissing' => true, 'alpa' => 0, 'potongan' => 0])
        ->and($cut['rekapNotes'])->toBe([['branch' => 'Tangerang', 'from' => '2026-09-21', 'to' => '2026-10-20']]);
});

it('leaves the Cabang that send a rekapitulasi out of the per-period totals', function () {
    recapData();
    $branch = Branch::factory()->forCompany($this->company)->create(['name' => 'Tangerang', 'attendance_source' => AttendanceSource::Rekap]);
    Employee::factory()->forCompany($this->company)->create(['full_name' => 'Rina', 'branch_id' => $branch->id, 'joined_on' => '2020-01-01']);

    $props = $this->actingAs($this->hr)->get('/attendance/recap?from=2026-10-01&to=2026-10-31')->viewData('page')['props'];

    // Rina has no scanner days, so no "no data" days are charged to the weeks either.
    expect(collect($props['periods'])->sum('noData'))->toBe($props['total']['noData'])
        ->and(collect($props['periods'])->sum('potongan'))->toBe($props['total']['potongan']);
});

it('exports the new columns', function () {
    recapData();

    $csv = $this->actingAs($this->hr)->get('/attendance/recap?from=2026-10-05&to=2026-10-06&export=csv')->streamedContent();

    expect($csv)->toContain('Alpa,Izin,Sakit,Telat,"Potongan (Rp)",Zona,Sumber')
        ->and($csv)->toContain('75000,Hijau,pemindai');
});

it('downloads the recap as a styled Excel workbook with the deduction grouped by thousands', function () {
    recapData();
    $this->sari->update(['full_name' => '=HYPERLINK("http://example.test")']);

    $response = $this->actingAs($this->hr)->get('/attendance/recap?from=2026-10-05&to=2026-10-06&export=xlsx');
    $response->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $path = tempnam(sys_get_temp_dir(), 'recap').'.xlsx';
    file_put_contents($path, $response->streamedContent());

    try {
        $book = IOFactory::load($path);
    } finally {
        unlink($path);
    }

    $sheet = $book->getSheetByName('Per karyawan');

    expect($book->getSheetNames())->toBe(['Per karyawan', 'Per cabang', 'Per departemen', 'Per periode']);

    // Header row 4: bold white on a dark fill, and the table is frozen under it.
    $header = $sheet->getStyle('A4');
    expect($sheet->getCell('A4')->getValue())->toBe('Karyawan')
        ->and($header->getFont()->getBold())->toBeTrue()
        ->and($header->getFont()->getColor()->getARGB())->toBe('FFFFFFFF')
        ->and($header->getFill()->getStartColor()->getARGB())->toBe('FF1F2937')
        ->and($sheet->getFreezePane())->toBe('B5');

    $columns = collect(range(1, 16))->mapWithKeys(fn (int $i): array => [$sheet->getCell([$i, 4])->getValue() => $i]);
    $rows = collect(range(5, 7))->mapWithKeys(fn (int $r): array => [$sheet->getCell([1, $r])->getValue() => $r]);
    $potongan = $columns['Potongan (Rp)'];

    // Sari: late the 5th and absent the 6th, Hijau: 75000, stored as a number and shown with a thousands separator.
    // (renamed above to look like a formula, which is the row we want to check anyway).
    $sari = $rows['=HYPERLINK("http://example.test")'];
    $cell = $sheet->getCell([$potongan, $sari]);
    expect($cell->getValue())->toBe(75000)
        ->and($sheet->getStyle($cell->getCoordinate())->getNumberFormat()->getFormatCode())->toBe('#,##0')
        ->and($sheet->getCell([$columns['Zona'], $sari])->getValue())->toBe('Hijau');

    // The total line is bold, and a name that looks like a formula stays text.
    $totalRow = 8;
    expect($sheet->getCell([1, $totalRow])->getValue())->toBe('Total (3 karyawan)')
        ->and($sheet->getStyle("A{$totalRow}")->getFont()->getBold())->toBeTrue()
        ->and($sheet->getCell([$potongan, $totalRow])->getValue())->toBe(125000)
        ->and($rows->keys()->contains('=HYPERLINK("http://example.test")'))->toBeTrue()
        ->and($sheet->getCell([1, $rows['=HYPERLINK("http://example.test")']])->getDataType())->toBe('s');
});
