<?php

use App\Enums\Role;
use App\Models\AttendanceEnrolMap;
use App\Models\AttendancePunch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/**
 * The attendance tab on an employee's profile: the month's timesheet, and manual corrections with
 * a reason and an audit trail (EMP-ATT).
 *
 * October 2026: the 5th is a Monday. The clock is fixed after every date used.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config([
        'attendance.device_timezone' => 'Asia/Jakarta',
        'attendance.schedule' => ['start' => '08:00', 'end' => '17:00', 'late_grace_minutes' => 10],
    ]);
    $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00', 'Asia/Jakarta'));
    $this->company = Company::factory()->create();
    $this->hr = tabUser(Role::HrAdmin, $this->company);
    $this->budi = Employee::factory()->forCompany($this->company)->create(['full_name' => 'Budi', 'joined_on' => '2020-01-01']);
    AttendanceEnrolMap::create(['company_id' => $this->company->id, 'employee_id' => $this->budi->id, 'device_sn' => 'SN1', 'pin' => 'P1']);
});

function tabUser(Role $role, ?Company $company = null): User
{
    $user = User::factory()->create(['company_id' => $company?->id]);
    $user->assignRole($role->value);

    return $user;
}

function tabScan(Employee $employee, string $local): AttendancePunch
{
    return AttendancePunch::create([
        'company_id' => $employee->company_id, 'employee_id' => $employee->id, 'device_sn' => 'SN1', 'pin' => 'P'.$employee->id,
        'scan_at' => CarbonImmutable::parse($local, 'Asia/Jakarta')->utc(), 'source' => 'manual-file',
    ]);
}

/**
 * Open the profile, then ask for the attendance prop the way the tab does (a partial reload), and
 * run the assertions against what comes back.
 */
function withTimesheet(User $user, Employee $employee, Closure $assertions, string $month = '2026-10'): void
{
    test()->actingAs($user)
        ->get(route('employees.show', $employee).'?month='.$month)
        ->assertInertia(fn (Assert $page) => $page->reloadOnly('attendance', fn (Assert $reload) => $reload->has('attendance', function (Assert $attendance) use ($assertions) {
            $assertions($attendance);
            $attendance->etc();
        })));
}

// --- The timesheet ---

it('is not sent with the profile until the tab asks for it', function () {
    $this->actingAs($this->hr)->get(route('employees.show', $this->budi))
        ->assertInertia(fn (Assert $page) => $page->missing('attendance')->where('canCorrectAttendance', true));
});

it('gives a month of statuses, worked time and the scans behind them', function () {
    tabScan($this->budi, '2026-10-05 07:55:00');
    tabScan($this->budi, '2026-10-05 17:05:00');
    tabScan($this->budi, '2026-10-06 08:30:00');
    tabScan($this->budi, '2026-10-06 17:00:00');
    tabScan(Employee::factory()->forCompany($this->company)->create(), '2026-10-07 08:00:00');

    withTimesheet($this->hr, $this->budi, function (Assert $reload) {
        $reload->where('month', '2026-10')
            ->where('prev', '2026-09')
            ->where('next', '2026-11')
            ->has('days', 31)
            ->where('days.4.date', '2026-10-05')
            ->where('days.4.status', 'present')
            ->where('days.4.in', '07:55')
            ->where('days.4.out', '17:05')
            ->where('days.4.minutes', 550)
            ->has('days.4.scans', 2)
            ->where('days.5.status', 'late')
            ->where('days.6.status', 'absent')
            ->where('totals.present', 1)
            ->where('totals.late', 1)
            ->where('totals.absent', 1)
            ->where('totals.minutes', 550 + 510);
    });
});

it('opens on the last month that has data when none is asked for', function () {
    tabScan($this->budi, '2026-09-10 08:00:00');

    $this->actingAs($this->hr)->get(route('employees.show', $this->budi))
        ->assertInertia(fn (Assert $page) => $page->reloadOnly('attendance', fn (Assert $reload) => $reload->where('attendance.month', '2026-09')->etc()));
});

it('lets an employee see their own timesheet but not correct it', function () {
    $owner = tabUser(Role::Employee, $this->company);
    $mine = Employee::factory()->forCompany($this->company)->create(['user_id' => $owner->id, 'joined_on' => '2020-01-01']);
    AttendanceEnrolMap::create(['company_id' => $this->company->id, 'employee_id' => $mine->id, 'device_sn' => 'SN1', 'pin' => 'P2']);
    AttendancePunch::create([
        'company_id' => $this->company->id, 'employee_id' => $mine->id, 'device_sn' => 'SN1', 'pin' => 'P2',
        'scan_at' => CarbonImmutable::parse('2026-10-05 08:00', 'Asia/Jakarta')->utc(), 'source' => 'manual-file',
    ]);

    withTimesheet($owner, $mine, fn (Assert $reload) => $reload->where('days.4.status', 'present'));

    $this->actingAs($owner)->get(route('employees.show', $mine))
        ->assertInertia(fn (Assert $page) => $page->where('canCorrectAttendance', false));
});

it('withholds the timesheet from a role that may view the profile but not attendance', function () {
    $supervisor = tabUser(Role::Supervisor, $this->company);

    $this->actingAs($supervisor)->get(route('employees.show', $this->budi))
        ->assertInertia(fn (Assert $page) => $page->where('attendance', null));
});

// --- Corrections ---

it('adds a manual punch with a reason, which turns an absent day into a present one', function () {
    tabScan(Employee::factory()->forCompany($this->company)->create(), '2026-10-06 08:00:00');

    $this->actingAs($this->hr)->post(route('punches.store', $this->budi), [
        'date' => '2026-10-06', 'time' => '08:05', 'reason' => 'Lupa scan, hadir menurut supervisor',
    ])->assertRedirect();

    $punch = AttendancePunch::where('employee_id', $this->budi->id)->firstOrFail();

    expect($punch->source)->toBe('manual')
        ->and($punch->reason)->toBe('Lupa scan, hadir menurut supervisor')
        ->and($punch->created_by_user_id)->toBe($this->hr->id)
        ->and($punch->scan_at->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'))->toBe('2026-10-06 08:05');

    withTimesheet($this->hr, $this->budi, fn (Assert $reload) => $reload
        ->where('days.5.status', 'present')
        ->where('days.5.scans.0.isManual', true));

    $log = Activity::where('log_name', 'attendance')->first();

    expect($log->description)->toBe('Punch added manually')->and($log->causer_id)->toBe($this->hr->id);
});

it('insists on a reason, a sensible time and a past date', function () {
    $post = fn (array $body) => $this->actingAs($this->hr)->post(route('punches.store', $this->budi), $body);

    $post(['date' => '2026-10-06', 'time' => '08:05'])->assertSessionHasErrors('reason');
    $post(['date' => '2026-10-06', 'time' => '08:05', 'reason' => 'ok'])->assertSessionHasErrors('reason');
    $post(['date' => '2026-10-06', 'time' => '25:99', 'reason' => 'Alasan cukup'])->assertSessionHasErrors('time');
    $post(['date' => '2026-12-01', 'time' => '08:00', 'reason' => 'Alasan cukup'])->assertSessionHasErrors('time');

    expect(AttendancePunch::count())->toBe(0);
});

it('voids a scan without deleting it, and the day recomputes without it', function () {
    tabScan($this->budi, '2026-10-05 07:55:00');
    $wrong = tabScan($this->budi, '2026-10-05 09:40:00');
    tabScan($this->budi, '2026-10-05 17:00:00');

    $this->actingAs($this->hr)->delete(route('punches.destroy', $wrong), ['reason' => 'Scan ganda karena salah jari'])->assertRedirect();

    expect(AttendancePunch::acrossCompanies()->count())->toBe(3)
        ->and($wrong->fresh()->voided_at)->not->toBeNull()
        ->and($wrong->fresh()->void_reason)->toBe('Scan ganda karena salah jari')
        ->and($wrong->fresh()->voided_by_user_id)->toBe($this->hr->id);

    withTimesheet($this->hr, $this->budi, fn (Assert $reload) => $reload
        ->where('days.4.scans.1.voided', true)
        ->where('days.4.scans.1.voidReason', 'Scan ganda karena salah jari')
        ->where('days.4.minutes', 545));
});

it('changes a scan by voiding it and adding the corrected one', function () {
    tabScan($this->budi, '2026-10-05 08:00:00');
    $late = tabScan($this->budi, '2026-10-05 18:30:00');

    $this->actingAs($this->hr)->put(route('punches.update', $late), [
        'date' => '2026-10-05', 'time' => '17:30', 'reason' => 'Jam mesin salah, pulang 17:30',
    ])->assertRedirect();

    expect($late->fresh()->voided_at)->not->toBeNull()
        ->and(AttendancePunch::acrossCompanies()->where('source', 'manual')->count())->toBe(1);

    withTimesheet($this->hr, $this->budi, fn (Assert $reload) => $reload
        ->where('days.4.out', '17:30')
        ->where('days.4.minutes', 570));

    expect(Activity::where('description', 'Punch changed')->count())->toBe(1);
});

it('refuses to void the same scan twice', function () {
    $punch = tabScan($this->budi, '2026-10-05 08:00:00');
    $this->actingAs($this->hr)->delete(route('punches.destroy', $punch), ['reason' => 'Salah scan']);

    $this->actingAs($this->hr)->delete(route('punches.destroy', $punch), ['reason' => 'Salah scan lagi'])
        ->assertSessionHasErrors('reason');
});

it('leaves a voided scan out of the calendar too', function () {
    $only = tabScan($this->budi, '2026-10-05 08:00:00');
    tabScan(Employee::factory()->forCompany($this->company)->create(), '2026-10-05 08:00:00');
    $this->actingAs($this->hr)->delete(route('punches.destroy', $only), ['reason' => 'Bukan scan Budi']);

    $this->actingAs($this->hr)->get('/attendance/calendar?view=day&date=2026-10-05')
        ->assertInertia(fn (Assert $page) => $page->where('rows.0.days.2026-10-05.status', 'absent'));
});

it('refuses corrections to anyone without the permission, including the employee themselves', function () {
    $owner = tabUser(Role::Employee, $this->company);
    $mine = Employee::factory()->forCompany($this->company)->create(['user_id' => $owner->id]);
    $punch = tabScan($mine, '2026-10-05 08:00:00');
    $body = ['date' => '2026-10-05', 'time' => '08:00', 'reason' => 'Alasan cukup'];

    foreach ([$owner, tabUser(Role::Supervisor, $this->company)] as $user) {
        $this->actingAs($user)->post(route('punches.store', $mine), $body)->assertForbidden();
        $this->actingAs($user)->put(route('punches.update', $punch), $body)->assertForbidden();
        $this->actingAs($user)->delete(route('punches.destroy', $punch), ['reason' => 'Alasan cukup'])->assertForbidden();
    }

    expect($punch->fresh()->voided_at)->toBeNull();
});

it('keeps another company\'s punches out of reach', function () {
    $other = Company::factory()->create();
    $outsider = Employee::factory()->forCompany($other)->create();
    $punch = AttendancePunch::create([
        'company_id' => $other->id, 'employee_id' => $outsider->id, 'device_sn' => 'SN1', 'pin' => 'P9',
        'scan_at' => CarbonImmutable::parse('2026-10-05 08:00', 'Asia/Jakarta')->utc(), 'source' => 'manual-file',
    ]);

    $this->actingAs($this->hr)->delete(route('punches.destroy', $punch->id), ['reason' => 'Alasan cukup'])->assertNotFound();
    $this->actingAs($this->hr)->post(route('punches.store', $outsider), ['date' => '2026-10-05', 'time' => '08:00', 'reason' => 'Alasan cukup'])->assertNotFound();
});

// --- The year at a glance ---

it('summarises a year per month, with the rate and totals', function () {
    tabScan($this->budi, '2026-10-05 08:00:00');
    tabScan($this->budi, '2026-10-05 17:00:00');
    tabScan($this->budi, '2026-10-06 08:30:00');
    tabScan($this->budi, '2026-10-06 17:00:00');

    $this->actingAs($this->hr)
        ->get(route('employees.show', $this->budi).'?period=year&year=2026')
        ->assertInertia(fn (Assert $page) => $page->reloadOnly('attendance', fn (Assert $reload) => $reload->has('attendance', function (Assert $attendance) {
            $attendance->where('period', 'year')
                ->where('year', 2026)
                ->where('prev', 2025)
                ->where('next', 2027)
                ->has('months', 12)
                ->where('months.9.present', 1)
                ->where('months.9.late', 1)
                ->where('months.9.minutes', 1050)
                ->where('months.0.rate', null)
                ->where('totals.present', 1)
                ->where('totals.late', 1)
                ->where('totals.rate', fn ($rate) => $rate > 0 && $rate <= 100)
                ->etc();
        })));
});

it('falls back to the year the data reaches when the year asked for is nonsense', function () {
    tabScan($this->budi, '2026-10-05 08:00:00');

    $this->actingAs($this->hr)
        ->get(route('employees.show', $this->budi).'?period=year&year=banana')
        ->assertInertia(fn (Assert $page) => $page->reloadOnly('attendance', fn (Assert $reload) => $reload->has('attendance', fn (Assert $attendance) => $attendance->where('year', 2026)->etc())));
});

it('keeps the month timesheet as the default and marks it as one', function () {
    tabScan($this->budi, '2026-10-05 08:00:00');

    withTimesheet($this->hr, $this->budi, fn (Assert $attendance) => $attendance->where('period', 'month')->where('month', '2026-10'));
});
