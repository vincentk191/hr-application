<?php

use App\Enums\AttendanceDayStatus;
use App\Enums\Role;
use App\Models\AttendanceEnrolMap;
use App\Models\AttendancePunch;
use App\Models\CalendarEvent;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Support\Attendance\DayClassifier;
use App\Support\Attendance\WorkingDays;
use App\Support\Attendance\WorkSchedule;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/**
 * Holidays and company events (ATT-WIZ): days on which attendance is not checked.
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
    $this->a = Company::factory()->create(['code' => 'CMP-A']);
    $this->b = Company::factory()->create(['code' => 'CMP-B']);
    $this->groupHr = eventUser(Role::HrAdmin);
    $this->hrA = eventUser(Role::HrAdmin, $this->a);
    $this->budi = eventEmployee($this->a, 'Budi');
    $this->sari = eventEmployee($this->b, 'Sari');
});

function eventUser(Role $role, ?Company $company = null): User
{
    $user = User::factory()->create(['company_id' => $company?->id]);
    $user->assignRole($role->value);

    return $user;
}

function eventEmployee(Company $company, string $name, ?Department $department = null): Employee
{
    $employee = Employee::factory()->forCompany($company)->create([
        'full_name' => $name, 'joined_on' => '2020-01-01', 'department_id' => $department?->id,
    ]);
    AttendanceEnrolMap::create(['company_id' => $company->id, 'employee_id' => $employee->id, 'device_sn' => 'SN1', 'pin' => 'P'.$employee->id]);

    return $employee;
}

function eventScan(Employee $employee, string $local): void
{
    AttendancePunch::create([
        'company_id' => $employee->company_id, 'employee_id' => $employee->id, 'device_sn' => 'SN1', 'pin' => 'P'.$employee->id,
        'scan_at' => CarbonImmutable::parse($local, 'Asia/Jakarta')->utc(), 'source' => 'manual-file',
    ]);
}

function holiday(array $attributes = []): CalendarEvent
{
    return CalendarEvent::create([
        'title' => 'Hari Libur', 'kind' => 'holiday', 'starts_on' => '2026-10-06', 'ends_on' => '2026-10-06', ...$attributes,
    ]);
}

/**
 * The status of someone on a day, as the day view of the calendar shows it to a viewer.
 *
 * @return array<string, mixed>
 */
function dayRecord(User $viewer, Employee $employee, string $date): array
{
    $rows = test()->actingAs($viewer)->get("/attendance/calendar?view=day&date={$date}")
        ->assertOk()->viewData('page')['props']['rows'];

    return collect($rows)->firstWhere('id', $employee->id)['days'][$date];
}

// --- The rule ---

it('treats a holiday as off for someone who did not scan, but still counts someone who did', function () {
    $classify = fn (bool $holiday, ?string $in, int $count) => (new DayClassifier)->classify(
        new WorkSchedule('08:00', '17:00', 10), true, true, true, false, $in, $in, $count, false, true, $holiday,
    );

    expect($classify(true, null, 0))->toBe(AttendanceDayStatus::Off)
        ->and($classify(false, null, 0))->toBe(AttendanceDayStatus::Absent)
        ->and($classify(true, '08:00', 1))->toBe(AttendanceDayStatus::Present);
});

it('shows a group-wide holiday as off for everyone, even when others came in', function () {
    holiday(['title' => 'Hari Kemerdekaan']);
    eventScan($this->budi, '2026-10-05 08:00:00');
    eventScan($this->budi, '2026-10-07 08:00:00');
    // Someone scanning on the holiday must not make it a normal working day for the rest.
    eventScan($this->sari, '2026-10-06 09:00:00');

    $record = dayRecord($this->groupHr, $this->budi, '2026-10-06');

    expect($record['status'])->toBe('off')->and($record['event'])->toBe('Hari Kemerdekaan');
    expect(dayRecord($this->groupHr, $this->sari, '2026-10-06')['status'])->not->toBe('absent');
});

it('applies a company event to that company only', function () {
    holiday(['company_id' => $this->a->id, 'title' => 'Ulang tahun CMP-A']);
    eventScan($this->budi, '2026-10-05 08:00:00');
    eventScan($this->sari, '2026-10-05 08:00:00');
    eventScan(eventEmployee($this->a, 'Pengisi A'), '2026-10-06 08:00:00');

    expect(dayRecord($this->groupHr, $this->budi, '2026-10-06')['status'])->toBe('off')
        ->and(dayRecord($this->groupHr, $this->sari, '2026-10-06')['status'])->toBe('absent');
});

it('applies a department event to that department only', function () {
    $ops = Department::factory()->forCompany($this->a)->create();
    $opsStaff = eventEmployee($this->a, 'Staf Ops', $ops);
    holiday(['company_id' => $this->a->id, 'department_id' => $ops->id, 'title' => 'Pelatihan Ops']);
    eventScan($this->budi, '2026-10-06 08:00:00');
    eventScan($this->budi, '2026-10-05 08:00:00');
    eventScan($opsStaff, '2026-10-05 08:00:00');

    expect(dayRecord($this->groupHr, $opsStaff, '2026-10-06')['status'])->toBe('off')
        ->and(dayRecord($this->groupHr, $this->budi, '2026-10-06')['status'])->toBe('present');
});

it('raises no absence issue on a holiday', function () {
    holiday();
    eventScan($this->budi, '2026-10-05 08:00:00');
    eventScan($this->budi, '2026-10-07 08:00:00');

    $issues = $this->actingAs($this->groupHr)->get('/attendance/issues?from=2026-10-06&to=2026-10-06&state=all')
        ->viewData('page')['props']['issues'];

    expect($issues)->toBe([]);
});

it('names the holiday on the employee\'s timesheet', function () {
    holiday(['title' => 'Cuti Bersama']);
    eventScan($this->budi, '2026-10-05 08:00:00');
    eventScan($this->budi, '2026-10-07 08:00:00');

    $this->actingAs($this->groupHr)->get(route('employees.show', $this->budi).'?month=2026-10')
        ->assertInertia(fn (Assert $page) => $page->reloadOnly('attendance', fn (Assert $reload) => $reload
            ->where('attendance.days.5.event', 'Cuti Bersama')
            ->where('attendance.days.5.status', 'off')
            ->etc()));
});

// --- Working days ---

it('counts working days as weekdays less the holidays that apply', function () {
    holiday(['title' => 'Libur', 'starts_on' => '2026-10-07', 'ends_on' => '2026-10-08']);
    holiday(['title' => 'Hanya B', 'company_id' => $this->b->id, 'starts_on' => '2026-10-09', 'ends_on' => '2026-10-09']);
    $days = app(WorkingDays::class);
    $from = CarbonImmutable::parse('2026-10-05');
    $to = CarbonImmutable::parse('2026-10-11');

    // Mon-Fri is five; the two holiday days leave three for company A, and B also loses Friday.
    expect($days->between($from, $to, $this->a->id, null))->toBe(3)
        ->and($days->between($from, $to, $this->b->id, null))->toBe(2)
        ->and($days->isWorkingDay(CarbonImmutable::parse('2026-10-07'), $this->a->id, null))->toBeFalse()
        ->and($days->isWorkingDay(CarbonImmutable::parse('2026-10-10'), $this->a->id, null))->toBeFalse() // Saturday
        ->and($days->isWorkingDay(CarbonImmutable::parse('2026-10-05'), $this->a->id, null))->toBeTrue();
});

// --- Adding ---

it('lets a parent-wide HR admin add a group-wide holiday', function () {
    $this->actingAs($this->groupHr)->post(route('calendar-events.store'), [
        'title' => 'Tahun Baru', 'kind' => 'holiday', 'starts_on' => '2026-12-31', 'ends_on' => '2027-01-01',
    ])->assertRedirect();

    $event = CalendarEvent::firstWhere('title', 'Tahun Baru');

    expect($event->company_id)->toBeNull()
        ->and($event->created_by_user_id)->toBe($this->groupHr->id)
        ->and(Activity::where('log_name', 'calendar')->where('subject_id', $event->id)->exists())->toBeTrue();
});

it('pins a company-bound HR admin\'s event to their own company, whatever they send', function () {
    $this->actingAs($this->hrA)->post(route('calendar-events.store'), [
        'title' => 'Acara A', 'kind' => 'event', 'starts_on' => '2026-10-20', 'ends_on' => '2026-10-20',
        'company_id' => $this->b->id,
    ])->assertRedirect();

    expect(CalendarEvent::firstWhere('title', 'Acara A')->company_id)->toBe($this->a->id);

    // And they cannot leave the company empty to make it group-wide.
    $this->actingAs($this->hrA)->post(route('calendar-events.store'), [
        'title' => 'Acara semua', 'kind' => 'event', 'starts_on' => '2026-10-21', 'ends_on' => '2026-10-21', 'company_id' => null,
    ])->assertRedirect();

    expect(CalendarEvent::firstWhere('title', 'Acara semua')->company_id)->toBe($this->a->id);
});

it('checks that a department belongs to the chosen company', function () {
    $other = Department::factory()->forCompany($this->b)->create();

    $this->actingAs($this->groupHr)->post(route('calendar-events.store'), [
        'title' => 'X', 'kind' => 'event', 'starts_on' => '2026-10-20', 'ends_on' => '2026-10-20',
        'company_id' => $this->a->id, 'department_id' => $other->id,
    ])->assertSessionHasErrors('department_id');

    $this->actingAs($this->groupHr)->post(route('calendar-events.store'), [
        'title' => 'X', 'kind' => 'event', 'starts_on' => '2026-10-20', 'ends_on' => '2026-10-20', 'department_id' => $other->id,
    ])->assertSessionHasErrors('department_id');

    expect(CalendarEvent::count())->toBe(0);
});

it('validates the title, kind and dates', function () {
    $post = fn (array $body) => $this->actingAs($this->groupHr)->post(route('calendar-events.store'), $body);
    $valid = ['title' => 'X', 'kind' => 'holiday', 'starts_on' => '2026-10-20', 'ends_on' => '2026-10-20'];

    $post([...$valid, 'title' => ''])->assertSessionHasErrors('title');
    $post([...$valid, 'kind' => 'party'])->assertSessionHasErrors('kind');
    $post([...$valid, 'ends_on' => '2026-10-19'])->assertSessionHasErrors('ends_on');
    $post([...$valid, 'ends_on' => '2026-12-31'])->assertSessionHasErrors('ends_on');
    $post([...$valid, 'starts_on' => 'besok'])->assertSessionHasErrors('starts_on');

    expect(CalendarEvent::count())->toBe(0);
});

it('refuses roles without the permission', function () {
    $this->actingAs(eventUser(Role::Supervisor, $this->a))->post(route('calendar-events.store'), [
        'title' => 'X', 'kind' => 'holiday', 'starts_on' => '2026-10-20', 'ends_on' => '2026-10-20',
    ])->assertForbidden();

    expect(CalendarEvent::count())->toBe(0);
});

// --- Removing ---

it('lets a company-bound admin remove their own company\'s events but not a group-wide or another company\'s', function () {
    $mine = holiday(['company_id' => $this->a->id, 'title' => 'Milik A']);
    $group = holiday(['title' => 'Semua']);
    $theirs = holiday(['company_id' => $this->b->id, 'title' => 'Milik B']);

    $this->actingAs($this->hrA)->delete(route('calendar-events.destroy', $group))->assertForbidden();
    $this->actingAs($this->hrA)->delete(route('calendar-events.destroy', $theirs))->assertForbidden();
    $this->actingAs($this->hrA)->delete(route('calendar-events.destroy', $mine))->assertRedirect();

    expect(CalendarEvent::pluck('title')->all())->toBe(['Semua', 'Milik B']);

    $this->actingAs($this->groupHr)->delete(route('calendar-events.destroy', $group))->assertRedirect();

    expect(CalendarEvent::count())->toBe(1);
});

// --- On the calendar page ---

it('lists the events in range with a label for who they cover, and who may delete them', function () {
    holiday(['title' => 'Semua']);
    holiday(['title' => 'Milik A', 'company_id' => $this->a->id, 'starts_on' => '2026-10-07', 'ends_on' => '2026-10-07']);
    holiday(['title' => 'Milik B', 'company_id' => $this->b->id, 'starts_on' => '2026-10-08', 'ends_on' => '2026-10-08']);
    holiday(['title' => 'Bulan lain', 'starts_on' => '2026-11-10', 'ends_on' => '2026-11-10']);

    $events = $this->actingAs($this->hrA)->get('/attendance/calendar?view=month&date=2026-10-15')
        ->viewData('page')['props']['events'];

    expect(collect($events)->pluck('title')->all())->toBe(['Semua', 'Milik A'])
        ->and(collect($events)->firstWhere('title', 'Semua')['scope'])->toBe('Semua perusahaan')
        ->and(collect($events)->firstWhere('title', 'Semua')['canDelete'])->toBeFalse()
        ->and(collect($events)->firstWhere('title', 'Milik A')['scope'])->toBe('CMP-A')
        ->and(collect($events)->firstWhere('title', 'Milik A')['canDelete'])->toBeTrue();
});

it('offers a company-bound user only their own company on the form', function () {
    $form = $this->actingAs($this->hrA)->get('/attendance/calendar')->viewData('page')['props']['eventForm'];

    expect($form['canBeGroupWide'])->toBeFalse()
        ->and(collect($form['companies'])->pluck('code')->all())->toBe(['CMP-A']);

    $groupForm = $this->actingAs($this->groupHr)->get('/attendance/calendar')->viewData('page')['props']['eventForm'];

    expect($groupForm['canBeGroupWide'])->toBeTrue()
        ->and($groupForm['companies'])->toHaveCount(2);
});

it('shows holidays and approved leave, weekends included, on days the log has not reached, and leaves the rest as no data', function () {
    $classify = fn (bool $holiday, bool $leave, bool $workday = true) => (new DayClassifier)->classify(
        new WorkSchedule('08:00', '17:00', 10), $workday, false, false, $leave, null, null, 0, false, true, $holiday,
    );

    expect($classify(true, false))->toBe(AttendanceDayStatus::Off)
        ->and($classify(true, true))->toBe(AttendanceDayStatus::Off)
        ->and($classify(false, true))->toBe(AttendanceDayStatus::Leave)
        ->and($classify(false, true, false))->toBe(AttendanceDayStatus::Leave)
        ->and($classify(false, false))->toBe(AttendanceDayStatus::NoData);
});

it('gives the day view each person\'s position and supervisor', function () {
    $boss = eventEmployee($this->a, 'Bos');
    $this->budi->update(['manager_id' => $boss->id]);

    $rows = $this->actingAs($this->hrA)->get('/attendance/calendar?view=day&date=2026-10-06')
        ->assertOk()->viewData('page')['props']['rows'];

    $row = collect($rows)->firstWhere('id', $this->budi->id);

    expect($row['manager'])->toBe('Bos')->and($row)->toHaveKeys(['position', 'manager']);
});
