<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The requests queue's filters, export and menu badge (REQ-DSH). Who is on leave is on the calendar's
 * side panel (AttendanceCalendarTest).
 *
 * Today is Tuesday 2026-10-20.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config([
        'attendance.device_timezone' => 'Asia/Jakarta',
        'attendance.schedule' => ['start' => '08:00', 'end' => '17:00', 'late_grace_minutes' => 10],
    ]);
    $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00', 'Asia/Jakarta'));

    $this->company = Company::factory()->create();
    $this->hr = lvUser(Role::HrAdmin, $this->company);
    $this->warehouse = Department::factory()->forCompany($this->company)->create(['name' => 'Gudang']);
    $this->office = Department::factory()->forCompany($this->company)->create(['name' => 'Kantor']);
    $this->budi = lvEmployee($this->company, 'Budi', $this->warehouse);
    $this->siti = lvEmployee($this->company, 'Siti', $this->warehouse);
    $this->dewi = lvEmployee($this->company, 'Dewi', $this->office);
    $this->rina = lvEmployee($this->company, 'Rina', $this->office);
});

function lvUser(Role $role, ?Company $company): User
{
    $user = User::factory()->create(['company_id' => $company?->id]);
    $user->assignRole($role->value);

    return $user;
}

function lvEmployee(Company $company, string $name, Department $department): Employee
{
    return Employee::factory()->inDepartment($department)->create(['full_name' => $name, 'joined_on' => '2020-01-01']);
}

/**
 * File a request for someone (HR is the approver: nobody has an Atasan) and optionally decide it.
 *
 * @param  array<string, mixed>  $overrides
 */
function lvRequest(Employee $employee, array $overrides = [], ?string $decision = null, ?string $note = null): LeaveRequest
{
    test()->actingAs(test()->hr)->post(route('leave-requests.store'), [
        'employee_id' => $employee->id, 'leave_type' => 'annual', 'start_on' => '2026-10-20', 'end_on' => '2026-10-21', ...$overrides,
    ])->assertSessionHasNoErrors();

    $leave = LeaveRequest::latest('id')->firstOrFail();

    if ($decision !== null) {
        test()->actingAs(test()->hr)->put(route('leave-requests.decision', $leave), ['decision' => $decision, 'note' => $note])->assertRedirect();
    }

    return $leave->refresh();
}

// --- The requests queue ---

function queue(string $query = ''): array
{
    return test()->actingAs(test()->hr)->get('/leave-requests'.$query)->assertOk()->viewData('page')['props'];
}

it('filters the queue by status, type, department and month', function () {
    $approved = lvRequest($this->budi, [], 'approve');
    $rejected = lvRequest($this->siti, ['leave_type' => 'sick'], 'reject', 'Tidak');
    $waiting = lvRequest($this->dewi, ['start_on' => '2026-11-03', 'end_on' => '2026-11-03']);

    $ids = fn (array $props): array => collect($props['requests'])->pluck('id')->sort()->values()->all();
    $all = collect([$approved, $rejected, $waiting])->pluck('id')->sort()->values()->all();

    expect($ids(queue()))->toBe($all)
        ->and($ids(queue('?status=approved')))->toBe([$approved->id])
        ->and($ids(queue('?status=rejected')))->toBe([$rejected->id])
        ->and($ids(queue('?status=waiting')))->toBe([$waiting->id])
        ->and($ids(queue('?type=sick')))->toBe([$rejected->id])
        ->and($ids(queue('?department='.urlencode($this->office->name))))->toBe([$waiting->id])
        ->and($ids(queue('?month=2026-11')))->toBe([$waiting->id])
        ->and($ids(queue('?month=2026-10')))->toBe(collect([$approved->id, $rejected->id])->sort()->values()->all());
});

it('has a "waiting on you" filter that only holds what this person may decide', function () {
    $boss = lvUser(Role::Supervisor, $this->company);
    $bossEmployee = Employee::factory()->forCompany($this->company)->create(['user_id' => $boss->id]);
    $this->budi->update(['manager_id' => $bossEmployee->id]);

    $mine = lvRequest($this->budi);
    lvRequest($this->siti);

    $asBoss = $this->actingAs($boss)->get('/leave-requests?status=mine')->viewData('page')['props'];

    expect(collect($asBoss['requests'])->pluck('id')->all())->toBe([$mine->id])
        ->and($asBoss['waitingOnMe'])->toBe(1);
});

it('ignores filters it does not know, rather than showing nothing', function () {
    lvRequest($this->budi);

    $props = queue('?status=bogus&type=nope&month=2026-13&department=abc');

    expect($props['requests'])->toHaveCount(1)
        ->and($props['filters'])->toBe(['status' => 'all', 'type' => null, 'department' => null, 'month' => null]);
});

it('says how many were approved this month', function () {
    lvRequest($this->budi, [], 'approve');
    lvRequest($this->siti, ['start_on' => '2026-10-22', 'end_on' => '2026-10-22']);

    expect(queue()['approvedThisMonth'])->toBe(1);
});

it('exports the queue with the same filters as the screen', function () {
    lvRequest($this->budi, ['leave_type' => 'sick']);
    lvRequest($this->siti);

    $csv = $this->actingAs($this->hr)->get('/leave-requests/export?type=sick')->assertOk()->streamedContent();

    expect($csv)->toContain('Pengajuan,Karyawan', 'Budi', 'Cuti sakit')->not->toContain('Siti');
});

it('puts the number waiting on you on every page, for the menu badge', function () {
    $boss = lvUser(Role::Supervisor, $this->company);
    $bossEmployee = Employee::factory()->forCompany($this->company)->create(['user_id' => $boss->id]);
    $this->budi->update(['manager_id' => $bossEmployee->id]);
    lvRequest($this->budi);

    $this->actingAs($boss)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('pendingForMe', 1));
    $this->actingAs($this->hr)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('pendingForMe', 0));
});

// --- The new-request form ---

it('opens the new-request form already filled in for the employee and day it was sent from', function () {
    $props = $this->actingAs($this->hr)->get('/leave-requests/create?employee='.$this->budi->id.'&date=2026-10-06')->assertOk()->viewData('page')['props'];

    expect($props['prefill'])->toBe(['employeeId' => $this->budi->id, 'date' => '2026-10-06']);
});

it('opens the new-request form empty, and ignores a date that is not one', function () {
    $props = $this->actingAs($this->hr)->get('/leave-requests/create?date=besok&employee=abc')->assertOk()->viewData('page')['props'];

    expect($props['prefill'])->toBe(['employeeId' => null, 'date' => null]);
});
