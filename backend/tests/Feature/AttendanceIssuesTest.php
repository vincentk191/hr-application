<?php

use App\Enums\Role;
use App\Models\AttendanceEnrolMap;
use App\Models\AttendanceIssueResolution;
use App\Models\AttendancePunch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/**
 * The attendance Issues screen (ATT-ISS): what is raised, what is not, and how an issue is
 * excused, with the audit trail that goes with it.
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
    $this->hr = issueUser(Role::HrAdmin, $this->company);
    $this->budi = issueEmployee($this->company, 'Budi');
});

function issueUser(Role $role, ?Company $company = null): User
{
    $user = User::factory()->create(['company_id' => $company?->id]);
    $user->assignRole($role->value);

    return $user;
}

function issueEmployee(Company $company, string $name): Employee
{
    $employee = Employee::factory()->forCompany($company)->create(['full_name' => $name, 'joined_on' => '2020-01-01']);
    AttendanceEnrolMap::create(['company_id' => $company->id, 'employee_id' => $employee->id, 'device_sn' => 'SN1', 'pin' => 'P'.$employee->id]);

    return $employee;
}

function issueScan(Employee $employee, string $local): AttendancePunch
{
    return AttendancePunch::create([
        'company_id' => $employee->company_id, 'employee_id' => $employee->id, 'device_sn' => 'SN1', 'pin' => 'P'.$employee->id,
        'scan_at' => CarbonImmutable::parse($local, 'Asia/Jakarta')->utc(), 'source' => 'manual-file',
    ]);
}

/**
 * A full, on-time day, so that a date counts as covered by the log and as a real working day.
 */
function coverDay(Company $company, string $date): void
{
    $filler = issueEmployee($company, 'Filler '.$date);
    issueScan($filler, "{$date} 08:00:00");
    issueScan($filler, "{$date} 17:00:00");
}

function issues(string $query = ''): TestResponse
{
    return test()->actingAs(test()->hr)->get('/attendance/issues'.$query);
}

/**
 * The issues for a given employee, as [type => count].
 *
 * @return array<string, int>
 */
function issueTypesFor(Employee $employee, string $query = '?from=2026-10-05&to=2026-10-09&state=all'): array
{
    $props = issues($query)->assertOk()->viewData('page')['props'];

    return collect($props['issues'])->where('employeeId', $employee->id)->countBy('type')->all();
}

// --- What is raised ---

it('raises each kind of exception for the right day', function () {
    foreach (['2026-10-05', '2026-10-06', '2026-10-07', '2026-10-08', '2026-10-09'] as $date) {
        coverDay($this->company, $date);
    }
    // Late on the 5th.
    issueScan($this->budi, '2026-10-05 09:30:00');
    issueScan($this->budi, '2026-10-05 17:00:00');
    // Left early on the 6th.
    issueScan($this->budi, '2026-10-06 08:00:00');
    issueScan($this->budi, '2026-10-06 15:00:00');
    // Never checked out on the 7th.
    issueScan($this->budi, '2026-10-07 08:00:00');
    // Absent on the 8th: nothing.
    // A doubled scan on the 9th.
    issueScan($this->budi, '2026-10-09 08:00:00');
    issueScan($this->budi, '2026-10-09 08:01:00');
    issueScan($this->budi, '2026-10-09 17:00:00');

    $props = issues('?from=2026-10-05&to=2026-10-09&state=all')->assertOk()->viewData('page')['props'];
    $mine = collect($props['issues'])->where('employeeId', $this->budi->id);

    expect($mine->firstWhere('date', '2026-10-05')['type'])->toBe('late')
        ->and($mine->firstWhere('date', '2026-10-06')['type'])->toBe('early_leave')
        ->and($mine->firstWhere('date', '2026-10-07')['type'])->toBe('missing_checkout')
        ->and($mine->firstWhere('date', '2026-10-08')['type'])->toBe('unapproved_absence')
        ->and($mine->firstWhere('date', '2026-10-09')['type'])->toBe('duplicate_punch')
        ->and($mine)->toHaveCount(5);
});

it('describes what was wrong in words', function () {
    coverDay($this->company, '2026-10-05');
    issueScan($this->budi, '2026-10-05 09:30:00');
    issueScan($this->budi, '2026-10-05 17:00:00');

    $issue = collect(issues('?from=2026-10-05&to=2026-10-05')->viewData('page')['props']['issues'])->firstWhere('employeeId', $this->budi->id);

    expect($issue['detail'])->toBe('Masuk 09:30, batas 08:10')
        ->and($issue['typeLabel'])->toBe('Terlambat');
});

it('raises nothing for days the log has not reached', function () {
    coverDay($this->company, '2026-10-05');

    // Budi has no scan on the 6th, but nothing covers the 6th either: that is not an absence.
    expect(issueTypesFor($this->budi, '?from=2026-10-05&to=2026-10-09&state=all'))
        ->toHaveKey('unapproved_absence', 1)
        ->not->toHaveKey('late');

    $onSixth = collect(issues('?from=2026-10-05&to=2026-10-09&state=all')->viewData('page')['props']['issues'])
        ->where('employeeId', $this->budi->id)->pluck('date')->all();

    expect($onSixth)->toBe(['2026-10-05']);
});

it('does not call someone with no scanner ID absent', function () {
    coverDay($this->company, '2026-10-05');
    $unmapped = Employee::factory()->forCompany($this->company)->create(['full_name' => 'Tanpa ID', 'joined_on' => '2020-01-01']);

    expect(issueTypesFor($unmapped))->toBe([]);
});

it('does not raise an absence for a day on approved leave', function () {
    coverDay($this->company, '2026-10-05');

    $this->actingAs($this->hr)->post(route('leave-requests.store'), [
        'employee_id' => $this->budi->id, 'leave_type' => 'annual', 'start_on' => '2026-10-05', 'end_on' => '2026-10-05', 'reason' => 'Perlu',
    ])->assertRedirect();
    $this->actingAs($this->hr)->put(route('leave-requests.decision', LeaveRequest::latest('id')->first()), ['decision' => 'approve'])->assertRedirect();

    expect(issueTypesFor($this->budi))->toBe([]);
});

it('raises a scan on approved leave as its own issue, and nothing else for that day', function () {
    coverDay($this->company, '2026-10-05');
    coverDay($this->company, '2026-10-06');

    $this->actingAs($this->hr)->post(route('leave-requests.store'), [
        'employee_id' => $this->budi->id, 'leave_type' => 'annual', 'start_on' => '2026-10-05', 'end_on' => '2026-10-06', 'reason' => 'Perlu',
    ])->assertRedirect();
    $this->actingAs($this->hr)->put(route('leave-requests.decision', LeaveRequest::latest('id')->first()), ['decision' => 'approve'])->assertRedirect();

    // The 5th: late and gone early. The 6th: a single scan, which would otherwise be a missing check-out.
    issueScan($this->budi, '2026-10-05 09:30:00');
    issueScan($this->budi, '2026-10-05 15:00:00');
    issueScan($this->budi, '2026-10-06 08:00:00');

    $mine = collect(issues('?from=2026-10-05&to=2026-10-06&state=all')->viewData('page')['props']['issues'])->where('employeeId', $this->budi->id);

    expect($mine->pluck('type', 'date')->all())->toBe(['2026-10-06' => 'scan_on_leave', '2026-10-05' => 'scan_on_leave'])
        ->and($mine->firstWhere('date', '2026-10-05')['detail'])->toContain('09:30–15:00')
        ->and($mine->firstWhere('date', '2026-10-06')['typeLabel'])->toBe('Scan saat cuti');

    // It can be excused like any other issue.
    $this->actingAs($this->hr)->post(route('attendance.issues.resolve'), [
        'reason' => 'Dipanggil masuk, cuti diganti',
        'items' => [['employee_id' => $this->budi->id, 'date' => '2026-10-05', 'type' => 'scan_on_leave']],
    ])->assertRedirect();

    expect(issueTypesFor($this->budi, '?from=2026-10-05&to=2026-10-06&state=open'))->toBe(['scan_on_leave' => 1]);
});

it('only calls a single scan a missing check-out once the person could have left', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-07 10:00', 'Asia/Jakarta'));
    coverDay($this->company, '2026-10-07');
    issueScan($this->budi, '2026-10-07 08:00:00');

    expect(issueTypesFor($this->budi, '?from=2026-10-07&to=2026-10-07'))->toBe([]);

    $this->travelTo(CarbonImmutable::parse('2026-10-07 17:30', 'Asia/Jakarta'));

    expect(issueTypesFor($this->budi, '?from=2026-10-07&to=2026-10-07'))->toBe(['missing_checkout' => 1]);
});

it('stops raising an issue once it is corrected', function () {
    coverDay($this->company, '2026-10-05');
    issueScan($this->budi, '2026-10-05 08:00:00');
    $double = issueScan($this->budi, '2026-10-05 08:01:00');
    issueScan($this->budi, '2026-10-05 17:00:00');

    expect(issueTypesFor($this->budi, '?from=2026-10-05&to=2026-10-05'))->toBe(['duplicate_punch' => 1]);

    $this->actingAs($this->hr)->delete(route('punches.destroy', $double), ['reason' => 'Scan ganda, jari ditekan dua kali'])->assertRedirect();

    expect(issueTypesFor($this->budi, '?from=2026-10-05&to=2026-10-05'))->toBe([]);
});

// --- Filters ---

it('filters by type, department and state, and counts per type', function () {
    coverDay($this->company, '2026-10-05');
    $ops = Department::factory()->forCompany($this->company)->create();
    $sari = issueEmployee($this->company, 'Sari');
    $sari->update(['department_id' => $ops->id]);
    issueScan($this->budi, '2026-10-05 09:30:00');
    issueScan($this->budi, '2026-10-05 17:00:00');
    issueScan($sari, '2026-10-05 08:00:00');
    issueScan($sari, '2026-10-05 15:00:00');

    $base = '?from=2026-10-05&to=2026-10-05';

    issues($base)->assertInertia(fn (Assert $page) => $page
        ->where('counts.late', 1)->where('counts.early_leave', 1)->where('total', 2)->etc());

    issues($base.'&types[]=late')->assertInertia(fn (Assert $page) => $page
        ->has('issues', 1)->where('issues.0.name', 'Budi')->where('counts.early_leave', 1)->etc());

    issues($base.'&departments[]='.$ops->id)->assertInertia(fn (Assert $page) => $page
        ->has('issues', 1)->where('issues.0.name', 'Sari')->etc());
});

it('keeps the range within what can be read at once', function () {
    issues('?from=2026-01-01&to=2026-10-09')->assertInertia(fn (Assert $page) => $page
        ->where('filters.to', '2026-10-09')
        ->where('filters.from', '2026-08-09')
        ->etc());
});

// --- Excusing ---

it('excuses issues with a reason, hides them from the open list and records who did it', function () {
    coverDay($this->company, '2026-10-05');
    issueScan($this->budi, '2026-10-05 09:30:00');
    issueScan($this->budi, '2026-10-05 17:00:00');

    $this->actingAs($this->hr)->post(route('attendance.issues.resolve'), [
        'reason' => 'Macet karena banjir, sudah dikonfirmasi',
        'items' => [['employee_id' => $this->budi->id, 'date' => '2026-10-05', 'type' => 'late']],
    ])->assertRedirect();

    expect(issueTypesFor($this->budi, '?from=2026-10-05&to=2026-10-05&state=open'))->toBe([]);

    $resolved = collect(issues('?from=2026-10-05&to=2026-10-05&state=resolved')->viewData('page')['props']['issues'])
        ->firstWhere('employeeId', $this->budi->id);

    expect($resolved['resolution']['reason'])->toBe('Macet karena banjir, sudah dikonfirmasi')
        ->and($resolved['resolution']['by'])->toBe($this->hr->name);

    $log = Activity::where('description', 'Issue excused')->first();

    expect($log->causer_id)->toBe($this->hr->id)
        ->and($log->properties['type'])->toBe('late');
});

it('excuses several at once with a single reason', function () {
    coverDay($this->company, '2026-10-05');
    $sari = issueEmployee($this->company, 'Sari');
    issueScan($this->budi, '2026-10-05 09:30:00');
    issueScan($this->budi, '2026-10-05 17:00:00');
    issueScan($sari, '2026-10-05 09:45:00');
    issueScan($sari, '2026-10-05 17:00:00');

    $this->actingAs($this->hr)->post(route('attendance.issues.resolve'), [
        'reason' => 'Gangguan transportasi umum',
        'items' => [
            ['employee_id' => $this->budi->id, 'date' => '2026-10-05', 'type' => 'late'],
            ['employee_id' => $sari->id, 'date' => '2026-10-05', 'type' => 'late'],
        ],
    ])->assertRedirect();

    expect(AttendanceIssueResolution::count())->toBe(2)
        ->and(Activity::where('description', 'Issue excused')->count())->toBe(2);
});

it('insists on a reason and a sensible batch', function () {
    $item = ['employee_id' => $this->budi->id, 'date' => '2026-10-05', 'type' => 'late'];

    $this->actingAs($this->hr)->post(route('attendance.issues.resolve'), ['items' => [$item]])->assertSessionHasErrors('reason');
    $this->actingAs($this->hr)->post(route('attendance.issues.resolve'), ['reason' => 'ok', 'items' => [$item]])->assertSessionHasErrors('reason');
    $this->actingAs($this->hr)->post(route('attendance.issues.resolve'), ['reason' => 'Alasan cukup', 'items' => []])->assertSessionHasErrors('items');
    $this->actingAs($this->hr)->post(route('attendance.issues.resolve'), ['reason' => 'Alasan cukup', 'items' => [[...$item, 'type' => 'bogus']]])->assertSessionHasErrors('items.0.type');

    expect(AttendanceIssueResolution::count())->toBe(0);
});

it('reopens an excused issue, and audits that too', function () {
    coverDay($this->company, '2026-10-05');
    issueScan($this->budi, '2026-10-05 09:30:00');
    issueScan($this->budi, '2026-10-05 17:00:00');
    $item = ['employee_id' => $this->budi->id, 'date' => '2026-10-05', 'type' => 'late'];

    $this->actingAs($this->hr)->post(route('attendance.issues.resolve'), ['reason' => 'Alasan awal', 'items' => [$item]]);
    $this->actingAs($this->hr)->post(route('attendance.issues.reopen'), ['reason' => 'Ternyata keliru dimaklumi', 'items' => [$item]])->assertRedirect();

    expect(AttendanceIssueResolution::count())->toBe(0)
        ->and(issueTypesFor($this->budi, '?from=2026-10-05&to=2026-10-05&state=open'))->toBe(['late' => 1])
        ->and(Activity::where('description', 'Issue reopened')->first()->properties['previous_reason'])->toBe('Alasan awal');
});

// --- Access ---

it('is open to roles that can view attendance and closed to the rest', function () {
    $this->actingAs($this->hr)->get('/attendance/issues')->assertOk();

    $this->actingAs(issueUser(Role::Supervisor, $this->company))->get('/attendance/issues')->assertForbidden();
    $this->actingAs(issueUser(Role::Employee, $this->company))->get('/attendance/issues')->assertForbidden();
});

it('refuses to excuse issues for someone without the correction permission', function () {
    $supervisor = issueUser(Role::Supervisor, $this->company);

    $this->actingAs($supervisor)->post(route('attendance.issues.resolve'), [
        'reason' => 'Alasan cukup',
        'items' => [['employee_id' => $this->budi->id, 'date' => '2026-10-05', 'type' => 'late']],
    ])->assertForbidden();

    expect(AttendanceIssueResolution::count())->toBe(0);
});

it('keeps another company\'s employees out of reach, and refuses the whole batch', function () {
    $other = Company::factory()->create();
    $outsider = issueEmployee($other, 'Orang Lain');

    $this->actingAs($this->hr)->post(route('attendance.issues.resolve'), [
        'reason' => 'Alasan cukup',
        'items' => [
            ['employee_id' => $this->budi->id, 'date' => '2026-10-05', 'type' => 'late'],
            ['employee_id' => $outsider->id, 'date' => '2026-10-05', 'type' => 'late'],
        ],
    ])->assertNotFound();

    expect(AttendanceIssueResolution::acrossCompanies()->count())->toBe(0);
});

it('shows a pinned HR admin only their own company\'s issues', function () {
    $other = Company::factory()->create();
    coverDay($this->company, '2026-10-05');
    coverDay($other, '2026-10-05');
    $outsider = issueEmployee($other, 'Orang Lain');
    issueScan($outsider, '2026-10-05 10:00:00');
    issueScan($outsider, '2026-10-05 17:00:00');
    issueScan($this->budi, '2026-10-05 10:00:00');
    issueScan($this->budi, '2026-10-05 17:00:00');

    $names = collect(issues('?from=2026-10-05&to=2026-10-05')->viewData('page')['props']['issues'])->pluck('name')->all();

    expect($names)->toContain('Budi')->not->toContain('Orang Lain');
});
