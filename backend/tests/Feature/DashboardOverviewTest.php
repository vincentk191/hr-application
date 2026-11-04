<?php

use App\Enums\Role;
use App\Models\AttendanceEnrolMap;
use App\Models\AttendancePunch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\RequestType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The employee's own overview on the dashboard: today's attendance, the month, the leave left and the requests still
 * being decided.
 *
 * The clock is fixed at 09:41 on Tuesday 20 October 2026 (Jakarta) for every test.
 */

/**
 * A signed-in employee with a record, whose Atasan has a login.
 *
 * @return array{0: User, 1: Employee, 2: User}
 */
function overviewEmployee(): array
{
    test()->seed(RoleSeeder::class);
    test()->travelTo(CarbonImmutable::parse('2026-10-20 09:41', 'Asia/Jakarta'));
    config([
        'attendance.device_timezone' => 'Asia/Jakarta',
        'attendance.schedule' => ['start' => '08:00', 'end' => '17:00', 'late_grace_minutes' => 10],
    ]);

    $company = Company::factory()->create();
    $supervisorUser = User::factory()->create(['company_id' => $company->id]);
    $supervisorUser->assignRole(Role::Supervisor->value);
    $supervisor = Employee::factory()->forCompany($company)->create(['user_id' => $supervisorUser->id, 'joined_on' => '2020-01-01']);

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole(Role::Employee->value);
    $employee = Employee::factory()->forCompany($company)->reportingTo($supervisor)->create([
        'user_id' => $user->id,
        'full_name' => 'Rina Wulandari',
        'joined_on' => '2020-01-01',
    ]);

    return [$user, $employee, $supervisorUser];
}

function overviewScan(Employee $employee, string $local): void
{
    AttendanceEnrolMap::firstOrCreate(
        ['employee_id' => $employee->id, 'device_sn' => 'SN1'],
        ['company_id' => $employee->company_id, 'pin' => 'P'.$employee->id],
    );

    AttendancePunch::create([
        'company_id' => $employee->company_id,
        'employee_id' => $employee->id,
        'device_sn' => 'SN1',
        'pin' => 'P'.$employee->id,
        'scan_at' => CarbonImmutable::parse($local, 'Asia/Jakarta')->utc(),
        'source' => 'manual-file',
    ]);
}

function overviewLeave(User $actor, Employee $employee, string $from, string $to, string $type = 'annual'): LeaveRequest
{
    test()->actingAs($actor)->post(route('leave-requests.store'), [
        'employee_id' => $employee->id,
        'leave_type' => $type,
        'start_on' => $from,
        'end_on' => $to,
        'reason' => 'Acara keluarga',
    ])->assertRedirect();

    return LeaveRequest::query()->latest('id')->firstOrFail();
}

it('has no personal overview for an account without an employee record', function () {
    $this->seed(RoleSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole(Role::SystemAdmin->value);

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('overview', null));
});

it('greets the employee by first name, with the day and the time of day', function () {
    [$user] = overviewEmployee();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.name', 'Rina')
            ->where('overview.greeting', 'Selamat pagi')
            ->where('overview.dateLabel', 'Selasa, 20 Oktober')
            ->where('overview.year', 2026));
});

it('shows today\'s first scan, no departure yet, and the working day', function () {
    [$user, $employee] = overviewEmployee();
    overviewScan($employee, '2026-10-20 08:02:00');

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.attendance.in', '08:02')
            ->where('overview.attendance.out', null)
            ->where('overview.attendance.status', 'present')
            ->where('overview.attendance.statusLabel', 'Tepat waktu')
            ->where('overview.attendance.workStart', '08:00')
            ->where('overview.attendance.workEnd', '17:00')
            ->where('overview.attendance.nowMinutes', 9 * 60 + 41));
});

it('marks a late arrival, and shows the departure once there is a second scan', function () {
    [$user, $employee] = overviewEmployee();
    overviewScan($employee, '2026-10-20 08:45:00');
    overviewScan($employee, '2026-10-20 09:30:00');

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.attendance.status', 'late')
            ->where('overview.attendance.tone', 'warn')
            ->where('overview.attendance.out', '09:30')
            ->where('overview.attendance.minutes', 45));
});

it('leaves attendance and the month out when the attendance module is switched off', function () {
    [$user] = overviewEmployee();
    config(['features.attendance' => false]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.attendance', null)
            ->where('overview.month', null)
            ->where('overview.name', 'Rina'));
});

it('works out the month as days attended over days attended plus absent', function () {
    [$user, $employee] = overviewEmployee();
    $colleague = Employee::factory()->forCompany($employee->company)->create(['joined_on' => '2020-01-01']);

    overviewScan($employee, '2026-10-05 08:00:00');
    overviewScan($employee, '2026-10-07 08:00:00');
    // Someone else came in on the 6th, so that was a working day and the employee was absent.
    overviewScan($colleague, '2026-10-06 08:00:00');

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.month.label', 'Oktober')
            ->where('overview.month.attended', 2)
            ->where('overview.month.expected', 3)
            ->where('overview.month.rate', 67));
});

it('subtracts only approved annual leave of this year from the entitlement', function () {
    [$user, $employee, $supervisorUser] = overviewEmployee();

    $approved = overviewLeave($user, $employee, '2026-11-02', '2026-11-04');
    $this->actingAs($supervisorUser)->put(route('leave-requests.decision', $approved), ['decision' => 'approve']);

    // One still waiting, one approved but not annual: neither comes off the balance.
    overviewLeave($user, $employee, '2026-12-01', '2026-12-02');
    $unpaid = overviewLeave($user, $employee, '2026-11-09', '2026-11-09', 'unpaid');
    $this->actingAs($supervisorUser)->put(route('leave-requests.decision', $unpaid), ['decision' => 'approve']);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.leaveBalance', ['entitlement' => 12, 'taken' => 3, 'remaining' => 9])
            ->where('overview.quickActions.0.hint', 'Sisa 9 hari'));
});

it('lists the employee\'s own open requests with the steps they go through', function () {
    [$user, $employee, $supervisorUser] = overviewEmployee();
    $other = Employee::factory()->forCompany($employee->company)->create(['joined_on' => '2020-01-01']);

    overviewLeave($user, $employee, '2026-11-03', '2026-11-07');
    $done = overviewLeave($user, $employee, '2026-11-16', '2026-11-16');
    $this->actingAs($supervisorUser)->put(route('leave-requests.decision', $done), ['decision' => 'approve']);

    $hr = User::factory()->create(['company_id' => $employee->company_id]);
    $hr->assignRole(Role::HrAdmin->value);
    overviewLeave($hr, $other, '2026-11-10', '2026-11-11');

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('overview.openRequests', 1)
            ->where('overview.openRequests.0.type', 'Cuti tahunan')
            ->where('overview.openRequests.0.dates', '03–07 Nov')
            ->where('overview.openRequests.0.size', '4 hari')
            ->where('overview.openRequests.0.waitingOn', 'Atasan')
            ->where('overview.openRequests.0.steps', [
                ['label' => 'Diajukan', 'state' => 'done'],
                ['label' => 'Atasan', 'state' => 'open'],
            ]));
});

it('shows a request that goes straight to HR with an HR step', function () {
    [$user, $employee] = overviewEmployee();
    $employee->update(['manager_id' => null]);

    overviewLeave($user, $employee, '2026-11-03', '2026-11-03');

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.openRequests.0.waitingOn', 'HR')
            ->where('overview.openRequests.0.steps.1', ['label' => 'HR', 'state' => 'open']));
});

it('points each shortcut at a request type this installation has', function () {
    [$user] = overviewEmployee();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.quickActions.0.type', 'annual')
            ->where('overview.quickActions.1.type', null)
            ->where('overview.quickActions.2.type', null)
            ->where('overview.quickActions.3.type', null));

    $izin = RequestType::create(['label' => 'Izin keluar', 'absence_kind' => 'izin', 'is_active' => true, 'sort_order' => 9]);
    $lembur = RequestType::create(['label' => 'Lembur', 'is_active' => true, 'sort_order' => 10]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.quickActions.1.type', $izin->code)
            ->where('overview.quickActions.2.type', $lembur->code));
});

it('opens the new request form on the type a shortcut names, and ignores one that is not offered', function () {
    [$user] = overviewEmployee();

    $this->actingAs($user)->get(route('leave-requests.create', ['type' => 'annual']))
        ->assertInertia(fn (Assert $page) => $page->where('prefill.type', 'annual'));

    $this->actingAs($user)->get(route('leave-requests.create', ['type' => 'nonsense']))
        ->assertInertia(fn (Assert $page) => $page->where('prefill.type', null));
});
