<?php

use App\Enums\AttendanceDayStatus;
use App\Enums\Role;
use App\Models\AttendanceEnrolMap;
use App\Models\AttendancePunch;
use App\Models\CalendarEvent;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Support\Attendance\DayClassifier;
use App\Support\Attendance\WorkSchedule;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The attendance calendar and its side panel (ATT-CAL, ATT-SID): how punches, leave and the
 * working week turn into a status for each person and day.
 *
 * Dates below are in October 2026: the 5th is a Monday.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    // A fixed clock, after every date used below: "today" is treated as still in progress, so
    // letting the real date drift into the test data would change what is expected.
    $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00', 'Asia/Jakarta'));
    config([
        'attendance.device_timezone' => 'Asia/Jakarta',
        'attendance.schedule' => ['start' => '08:00', 'end' => '17:00', 'late_grace_minutes' => 10],
    ]);
    $this->company = Company::factory()->create();
    $this->hr = calendarUser(Role::HrAdmin, $this->company);
    $this->budi = withScannerId(Employee::factory()->forCompany($this->company)->create(['full_name' => 'Budi', 'joined_on' => '2020-01-01']));
});

function calendarUser(Role $role, ?Company $company = null): User
{
    $user = User::factory()->create(['company_id' => $company?->id]);
    $user->assignRole($role->value);

    return $user;
}

/**
 * Give an employee a scanner ID. Without one the calendar cannot judge them at all, which is
 * its own rule, so every employee a test expects to be judged needs this.
 */
function withScannerId(Employee $employee): Employee
{
    AttendanceEnrolMap::firstOrCreate(
        ['employee_id' => $employee->id, 'device_sn' => 'SN1'],
        ['company_id' => $employee->company_id, 'pin' => 'P'.$employee->id],
    );

    return $employee;
}

/**
 * Record a scan at a local (Jakarta) time.
 */
function scan(Employee $employee, string $local): void
{
    withScannerId($employee);

    AttendancePunch::create([
        'company_id' => $employee->company_id,
        'employee_id' => $employee->id,
        'device_sn' => 'SN1',
        'pin' => 'P'.$employee->id,
        'scan_at' => CarbonImmutable::parse($local, 'Asia/Jakarta')->utc(),
        'source' => 'manual-file',
    ]);
}

function statusOn(array $page, int $employeeId, string $date): string
{
    return collect($page['props']['rows'])->firstWhere('id', $employeeId)['days'][$date]['status'];
}

function calendar(string $query = ''): TestResponse
{
    return test()->actingAs(test()->hr)->get('/attendance/calendar'.$query);
}

// --- The rules, on their own ---

it('classifies a day by the documented order of rules', function (array $input, AttendanceDayStatus $expected) {
    $input = [
        'workday' => true, 'covered' => true, 'anyone' => true, 'leave' => false,
        'in' => null, 'out' => null, 'count' => 0, 'working' => false, 'scannerId' => true, ...$input,
    ];

    $status = (new DayClassifier)->classify(
        new WorkSchedule('08:00', '17:00', 10),
        $input['workday'], $input['covered'], $input['anyone'], $input['leave'],
        $input['in'], $input['out'], $input['count'], $input['working'], $input['scannerId'],
    );

    expect($status)->toBe($expected);
})->with([
    'on time, full day' => [['in' => '07:55', 'out' => '17:05', 'count' => 2], AttendanceDayStatus::Present],
    'arrived within the grace' => [['in' => '08:10', 'out' => '17:00', 'count' => 2], AttendanceDayStatus::Present],
    'arrived after the grace' => [['in' => '08:11', 'out' => '17:00', 'count' => 2], AttendanceDayStatus::Late],
    'left before the end' => [['in' => '07:55', 'out' => '16:30', 'count' => 2], AttendanceDayStatus::EarlyLeave],
    'late beats early leave' => [['in' => '09:00', 'out' => '16:00', 'count' => 2], AttendanceDayStatus::Late],
    'a lone punch is not an early departure' => [['in' => '07:55', 'out' => '07:55', 'count' => 1], AttendanceDayStatus::Present],
    'still at work today is not early leave' => [['in' => '07:55', 'out' => '12:00', 'count' => 2, 'working' => true], AttendanceDayStatus::Present],
    'nobody came, workday, no leave' => [[], AttendanceDayStatus::Absent],
    'nobody came, on approved leave' => [['leave' => true], AttendanceDayStatus::Leave],
    'came in despite approved leave' => [['leave' => true, 'in' => '08:00', 'out' => '17:00', 'count' => 2], AttendanceDayStatus::Present],
    'no scanner ID mapped, on a workday inside the data' => [['scannerId' => false], AttendanceDayStatus::NoData],
    'no scanner ID mapped, but approved leave is known' => [['scannerId' => false, 'leave' => true], AttendanceDayStatus::Leave],
    'no scanner ID mapped, on a weekend' => [['scannerId' => false, 'workday' => false], AttendanceDayStatus::Off],
    'a weekend with no punch' => [['workday' => false], AttendanceDayStatus::Off],
    'a weekend with a punch still counts' => [['workday' => false, 'in' => '09:00', 'out' => '12:00', 'count' => 2], AttendanceDayStatus::Late],
    'a day nobody at all scanned' => [['anyone' => false], AttendanceDayStatus::Off],
    'a day the log has not reached' => [['covered' => false], AttendanceDayStatus::NoData],
    'a day the log has not reached, but approved leave is known' => [['covered' => false, 'leave' => true], AttendanceDayStatus::Leave],
]);

// --- The grid ---

it('shows each person\'s status per day from their punches', function () {
    scan($this->budi, '2026-10-05 07:55:00');
    scan($this->budi, '2026-10-05 17:03:00');
    scan($this->budi, '2026-10-06 08:30:00');
    scan($this->budi, '2026-10-06 17:00:00');
    scan($this->budi, '2026-10-07 07:50:00');
    scan($this->budi, '2026-10-07 15:00:00');
    // Someone else scanning on the 8th keeps the 8th a real day, on which Budi is absent.
    scan(Employee::factory()->forCompany($this->company)->create(['joined_on' => '2020-01-01']), '2026-10-08 08:00:00');
    scan($this->budi, '2026-10-09 07:55:00');

    calendar('?view=month&date=2026-10-15')
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $data = $page->toArray();

            expect(statusOn($data, $this->budi->id, '2026-10-05'))->toBe('present')
                ->and(statusOn($data, $this->budi->id, '2026-10-06'))->toBe('late')
                ->and(statusOn($data, $this->budi->id, '2026-10-07'))->toBe('early_leave')
                ->and(statusOn($data, $this->budi->id, '2026-10-08'))->toBe('absent')
                ->and(statusOn($data, $this->budi->id, '2026-10-04'))->toBe('off');
        });
});

it('reports the time in and out in the scanner\'s timezone', function () {
    scan($this->budi, '2026-10-05 07:55:00');
    scan($this->budi, '2026-10-05 17:03:00');

    calendar('?view=day&date=2026-10-05')->assertInertia(fn (Assert $page) => $page
        ->where('rows.0.days.2026-10-05.in', '07:55')
        ->where('rows.0.days.2026-10-05.out', '17:03'));
});

it('puts a late-evening scan on its own local day', function () {
    // 23:50 in Jakarta is 16:50 UTC the same day, but 00:30 the next local day is 17:30 UTC —
    // still the previous UTC date. Both must land on their local date.
    scan($this->budi, '2026-10-05 23:50:00');
    scan($this->budi, '2026-10-06 00:30:00');

    calendar('?view=week&date=2026-10-05')->assertInertia(fn (Assert $page) => $page
        ->where('rows.0.days.2026-10-05.punches', 1)
        ->where('rows.0.days.2026-10-06.punches', 1));
});

it('never reads days after the last captured data as absence', function () {
    scan($this->budi, '2026-10-05 08:00:00');
    scan($this->budi, '2026-10-05 17:00:00');

    calendar('?view=week&date=2026-10-05')->assertInertia(function (Assert $page) {
        $data = $page->toArray();

        expect(statusOn($data, $this->budi->id, '2026-10-06'))->toBe('no_data')
            ->and(statusOn($data, $this->budi->id, '2026-10-09'))->toBe('no_data')
            ->and($data['props']['dataThrough'])->toBe('2026-10-05');
    });
});

it('treats a day nobody scanned on as off rather than marking everyone absent', function () {
    $other = Employee::factory()->forCompany($this->company)->create(['joined_on' => '2020-01-01']);
    foreach ([$this->budi, $other] as $employee) {
        scan($employee, '2026-10-05 08:00:00');
        scan($employee, '2026-10-05 17:00:00');
        scan($employee, '2026-10-07 08:00:00');
        scan($employee, '2026-10-07 17:00:00');
    }

    calendar('?view=week&date=2026-10-05')->assertInertia(function (Assert $page) {
        $data = $page->toArray();

        // Tuesday 6th: a weekday with no scan from anyone, inside the data. A holiday, not absence.
        expect(statusOn($data, $this->budi->id, '2026-10-06'))->toBe('off');
    });
});

it('shows approved leave, and ignores leave that is still waiting', function () {
    $other = Employee::factory()->forCompany($this->company)->create(['joined_on' => '2020-01-01']);
    scan($other, '2026-10-05 08:00:00');
    scan($other, '2026-10-06 08:00:00');
    scan($other, '2026-10-07 08:00:00');

    $leave = function (string $on) {
        test()->actingAs(test()->hr)->post(route('leave-requests.store'), [
            'employee_id' => test()->budi->id, 'leave_type' => 'annual', 'start_on' => $on, 'end_on' => $on, 'reason' => 'Perlu',
        ])->assertRedirect();

        return LeaveRequest::query()->latest('id')->first();
    };

    $approved = $leave('2026-10-06');
    test()->actingAs(test()->hr)->put(route('leave-requests.decision', $approved), ['decision' => 'approve'])->assertRedirect();
    $leave('2026-10-07');

    calendar('?view=week&date=2026-10-05')->assertInertia(function (Assert $page) {
        $data = $page->toArray();

        expect(statusOn($data, $this->budi->id, '2026-10-06'))->toBe('leave')
            ->and(statusOn($data, $this->budi->id, '2026-10-07'))->toBe('absent');
    });
});

it('does not judge someone before they joined or after they left', function () {
    $newHire = withScannerId(Employee::factory()->forCompany($this->company)->create(['full_name' => 'Baru', 'joined_on' => '2026-10-07']));
    scan($this->budi, '2026-10-05 08:00:00');
    scan($this->budi, '2026-10-08 08:00:00');

    calendar('?view=week&date=2026-10-05')->assertInertia(function (Assert $page) use ($newHire) {
        $data = $page->toArray();

        expect(statusOn($data, $newHire->id, '2026-10-05'))->toBe('off')
            ->and(statusOn($data, $newHire->id, '2026-10-08'))->toBe('absent');
    });
});

it('does not call someone absent when no scanner ID is mapped to them', function () {
    $unmapped = Employee::factory()->forCompany($this->company)->create(['full_name' => 'Tanpa ID', 'joined_on' => '2020-01-01']);
    scan($this->budi, '2026-10-05 08:00:00');
    scan($this->budi, '2026-10-06 08:00:00');

    calendar('?view=week&date=2026-10-05')->assertInertia(function (Assert $page) use ($unmapped) {
        $data = $page->toArray();
        $row = collect($data['props']['rows'])->firstWhere('id', $unmapped->id);

        expect($row['hasScannerId'])->toBeFalse()
            ->and($row['days']['2026-10-05']['status'])->toBe('no_data')
            ->and(collect($data['props']['rows'])->firstWhere('id', $this->budi->id)['hasScannerId'])->toBeTrue()
            ->and($data['props']['summary']['counts']['absent'])->toBe(0);
    });
});

// --- Views, navigation, filters ---

it('opens on the last day that has data when no date is given', function () {
    scan($this->budi, '2026-10-05 08:00:00');
    scan($this->budi, '2026-09-10 08:00:00');

    calendar()->assertInertia(fn (Assert $page) => $page
        ->where('view', 'month')
        ->where('anchor', '2026-10-05')
        ->where('range.from', '2026-10-01')
        ->where('range.to', '2026-10-31'));
});

it('covers a Monday-to-Sunday week and a single day', function () {
    calendar('?view=week&date=2026-10-07')->assertInertia(fn (Assert $page) => $page
        ->where('range.from', '2026-10-05')->where('range.to', '2026-10-11')->has('dates', 7));

    calendar('?view=day&date=2026-10-07')->assertInertia(fn (Assert $page) => $page
        ->where('range.from', '2026-10-07')->where('range.to', '2026-10-07')->has('dates', 1));
});

it('totals the year per month and sends no grid', function () {
    scan($this->budi, '2026-10-05 08:00:00');
    scan($this->budi, '2026-10-05 17:00:00');

    calendar('?view=year&date=2026-10-05')->assertInertia(fn (Assert $page) => $page
        ->has('months', 12)
        ->where('rows', [])
        ->where('months.9.counts.present', 1));
});

it('falls back to the month view for an unknown view', function () {
    calendar('?view=decade&date=2026-10-05')->assertInertia(fn (Assert $page) => $page->where('view', 'month'));
});

it('filters by department, search and status', function () {
    $ops = Department::factory()->forCompany($this->company)->create();
    $sari = Employee::factory()->inDepartment($ops)->create(['full_name' => 'Sari', 'joined_on' => '2020-01-01']);
    scan($this->budi, '2026-10-05 08:00:00');
    scan($this->budi, '2026-10-05 17:00:00');
    scan($sari, '2026-10-05 09:30:00');
    scan($sari, '2026-10-05 17:00:00');

    calendar('?view=day&date=2026-10-05&departments[]='.$ops->id)
        ->assertInertia(fn (Assert $page) => $page->has('rows', 1)->where('rows.0.name', 'Sari'));

    calendar('?view=day&date=2026-10-05&search=Bud')
        ->assertInertia(fn (Assert $page) => $page->has('rows', 1)->where('rows.0.name', 'Budi'));

    calendar('?view=day&date=2026-10-05&statuses[]=late')
        ->assertInertia(fn (Assert $page) => $page->has('rows', 1)->where('rows.0.name', 'Sari'));
});

// --- The side panel ---

it('computes the side panel from the same rows and filters as the grid', function () {
    $ops = Department::factory()->forCompany($this->company)->create();
    $sari = Employee::factory()->inDepartment($ops)->create(['joined_on' => '2020-01-01']);
    scan($this->budi, '2026-10-05 08:00:00');
    scan($this->budi, '2026-10-05 17:00:00');
    scan($sari, '2026-10-05 09:30:00');
    scan($sari, '2026-10-05 17:00:00');

    calendar('?view=day&date=2026-10-05')->assertInertia(fn (Assert $page) => $page
        ->where('summary.counts.present', 1)
        ->where('summary.counts.late', 1)
        ->where('summary.attended', 2)
        ->where('summary.employees', 2));

    calendar('?view=day&date=2026-10-05&departments[]='.$ops->id)->assertInertia(fn (Assert $page) => $page
        ->where('summary.counts.present', 0)
        ->where('summary.counts.late', 1)
        ->where('summary.employees', 1));
});

// --- Access and tenancy ---

it('is open to roles with the permission and closed to the rest', function () {
    $this->actingAs($this->hr)->get('/attendance/calendar')->assertOk();

    $this->actingAs(calendarUser(Role::Supervisor, $this->company))->get('/attendance/calendar')->assertForbidden();
    $this->actingAs(calendarUser(Role::Employee, $this->company))->get('/attendance/calendar')->assertForbidden();
});

it('shows a company-pinned HR admin only their own company', function () {
    $other = Company::factory()->create();
    $outsider = Employee::factory()->forCompany($other)->create(['full_name' => 'Orang Lain', 'joined_on' => '2020-01-01']);
    scan($this->budi, '2026-10-05 08:00:00');
    scan($outsider, '2026-10-05 08:00:00');

    calendar('?view=day&date=2026-10-05')->assertInertia(fn (Assert $page) => $page
        ->has('rows', 1)
        ->where('rows.0.name', 'Budi'));
});

it('lets the sidebar know who can open the calendar', function () {
    $this->actingAs($this->hr)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canViewAttendance', true));

    $this->actingAs(calendarUser(Role::Employee, $this->company))->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canViewAttendance', false));
});

it('summarises each day of a week: who checked in, who was late, and what the day is', function () {
    scan($this->budi, '2026-10-05 08:00:00');
    scan($this->budi, '2026-10-06 08:30:00');
    $other = withScannerId(Employee::factory()->forCompany($this->company)->create(['joined_on' => '2020-01-01']));
    scan($other, '2026-10-05 08:00:00');

    calendar('?view=week&date=2026-10-05')->assertInertia(fn (Assert $page) => $page
        ->has('daily', 7)
        ->where('daily.0.attended', 2)
        ->where('daily.0.late', 0)
        ->where('daily.1.attended', 1)
        ->where('daily.1.late', 1)
        ->where('daily.0.covered', true)
        ->where('daily.0.isWorkday', true)
        ->where('daily.5.isWorkday', false)
        ->where('daily.5.covered', false));
});

it('does not call a day beyond the data a day of nobody', function () {
    scan($this->budi, '2026-10-05 08:00:00');

    calendar('?view=week&date=2026-10-12')->assertInertia(fn (Assert $page) => $page
        ->where('daily.0.covered', false)
        ->where('daily.0.attended', 0));
});

it('names a holiday that covers everyone, but not one that covers a single department', function () {
    CalendarEvent::create(['title' => 'Hari Libur', 'kind' => 'holiday', 'starts_on' => '2026-10-06', 'ends_on' => '2026-10-06']);
    $dept = Department::factory()->forCompany($this->company)->create();
    CalendarEvent::create(['title' => 'Acara Gudang', 'kind' => 'event', 'starts_on' => '2026-10-07', 'ends_on' => '2026-10-07', 'company_id' => $this->company->id, 'department_id' => $dept->id]);
    scan($this->budi, '2026-10-05 08:00:00');

    calendar('?view=month&date=2026-10-05')->assertInertia(fn (Assert $page) => $page
        ->has('daily', 31)
        ->where('daily.5.holiday', 'Hari Libur')
        ->where('daily.6.holiday', null));
});

it('gives each month of the year a rate, working days and the people-day counts', function () {
    scan($this->budi, '2026-10-05 08:00:00');
    scan($this->budi, '2026-10-06 08:30:00');
    CalendarEvent::create(['title' => 'Libur', 'kind' => 'holiday', 'starts_on' => '2026-10-07', 'ends_on' => '2026-10-07']);

    calendar('?view=year&date=2026-10-05')->assertInertia(fn (Assert $page) => $page
        ->where('months.9.attended', 2)
        ->where('months.9.late', 1)
        ->where('months.9.workdays', 21)
        ->where('months.9.rate', fn ($rate) => $rate > 0 && $rate <= 100)
        ->where('months.0.rate', null)
        ->where('months.0.workdays', 22));
});
