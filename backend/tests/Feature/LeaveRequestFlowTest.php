<?php

use App\Enums\LeaveRequestStage;
use App\Enums\Role;
use App\Enums\WorkflowStepStatus;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Leave requests end to end (REQ-WIZ, REQ-DSH, REQ-DLG) — and with them, the first real
 * exercise of the approval workflow engine through HTTP rather than in isolation.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
});

function userWith(Role $role, ?Company $company = null): User
{
    $user = User::factory()->create(['company_id' => $company?->id]);
    $user->assignRole($role->value);

    return $user;
}

/**
 * An employee whose supervisor has a login, so the chain gets both of its steps.
 *
 * @return array{0: Employee, 1: User}
 */
function employeeUnderSupervisor(Company $company): array
{
    $supervisorUser = userWith(Role::Supervisor, $company);
    $supervisor = Employee::factory()->forCompany($company)->create([
        'user_id' => $supervisorUser->id,
    ]);
    $employee = Employee::factory()->forCompany($company)->reportingTo($supervisor)->create();

    return [$employee, $supervisorUser];
}

function fileLeave(User $actor, Employee $employee): LeaveRequest
{
    test()->actingAs($actor)->post(route('leave-requests.store'), [
        'employee_id' => $employee->id,
        'leave_type' => 'annual',
        'start_on' => '2026-11-02',
        'end_on' => '2026-11-04',
        'reason' => 'Acara keluarga',
    ])->assertRedirect(route('leave-requests.index'));

    return LeaveRequest::latest('id')->firstOrFail();
}

it('sends a request to the employee\'s Atasan only, not on to HR', function () {
    [$employee, $supervisorUser] = employeeUnderSupervisor($this->company);
    $hr = userWith(Role::HrAdmin, $this->company);

    $leave = fileLeave($hr, $employee);
    $steps = $leave->workflow->steps;

    expect($leave->days)->toBe(3)
        ->and($leave->workflow->stage())->toBe(LeaveRequestStage::Submitted)
        ->and($steps)->toHaveCount(1)
        ->and($steps[0]->label)->toBe('Atasan langsung')
        ->and($steps[0]->approver_user_id)->toBe($supervisorUser->id)
        ->and($steps[0]->status)->toBe(WorkflowStepStatus::Pending);
});

it('goes straight to HR only when the employee has nobody who could approve', function () {
    $hr = userWith(Role::HrAdmin, $this->company);
    $noAtasan = Employee::factory()->forCompany($this->company)->create();

    // No Atasan on record.
    $leave = fileLeave($hr, $noAtasan);
    expect($leave->workflow->steps)->toHaveCount(1)
        ->and($leave->workflow->steps[0]->approver_role)->toBe('hr-admin')
        ->and($leave->workflow->steps[0]->status)->toBe(WorkflowStepStatus::Pending);

    // An Atasan with no login cannot decide anything.
    $boss = Employee::factory()->forCompany($this->company)->create();
    $loginless = Employee::factory()->forCompany($this->company)->reportingTo($boss)->create();
    expect(fileLeave($hr, $loginless)->workflow->steps[0]->approver_role)->toBe('hr-admin');

    // Nor can one whose account is deactivated.
    $inactive = userWith(Role::Supervisor, $this->company);
    $inactive->forceFill(['is_active' => false])->save();
    $bossTwo = Employee::factory()->forCompany($this->company)->create(['user_id' => $inactive->id]);
    $orphan = Employee::factory()->forCompany($this->company)->reportingTo($bossTwo)->create();
    expect(fileLeave($hr, $orphan)->workflow->steps[0]->approver_role)->toBe('hr-admin');
});

it('lets the Atasan alone settle the request', function () {
    [$employee, $supervisorUser] = employeeUnderSupervisor($this->company);
    $leave = fileLeave(userWith(Role::HrAdmin, $this->company), $employee);

    $this->actingAs($supervisorUser)
        ->put(route('leave-requests.decision', $leave), ['decision' => 'approve'])
        ->assertRedirect();

    $leave->refresh();

    expect($leave->workflow->stage())->toBe(LeaveRequestStage::Approved)
        ->and($leave->workflow->steps[0]->status)->toBe(WorkflowStepStatus::Approved)
        ->and($leave->workflow->isComplete())->toBeTrue();
});

it('does not let HR Admin decide a request that belongs to someone else\'s Atasan', function () {
    [$employee] = employeeUnderSupervisor($this->company);
    $hr = userWith(Role::HrAdmin, $this->company);
    $leave = fileLeave($hr, $employee);

    $this->actingAs($hr)
        ->put(route('leave-requests.decision', $leave), ['decision' => 'approve'])
        ->assertForbidden();
    $this->actingAs($hr)
        ->put(route('leave-requests.decision', $leave), ['decision' => 'reject', 'note' => 'Tidak'])
        ->assertForbidden();

    expect($leave->refresh()->workflow->stage())->toBe(LeaveRequestStage::Submitted)
        ->and($leave->workflow->steps[0]->status)->toBe(WorkflowStepStatus::Pending);
});

it('still shows HR Admin every request, and marks none of them as theirs to decide', function () {
    [$employee] = employeeUnderSupervisor($this->company);
    $hr = userWith(Role::HrAdmin, $this->company);
    fileLeave($hr, $employee);

    $this->actingAs($hr)->get(route('leave-requests.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('requests', 1)
            ->where('requests.0.canDecide', false)
            ->where('requests.0.waitingOn', 'Atasan langsung')
            ->where('waitingOnMe', 0));
});

it('has an HR Admin decide for the people who report to them', function () {
    $hrBoss = userWith(Role::HrAdmin, $this->company);
    $hrBossEmployee = Employee::factory()->forCompany($this->company)->create(['user_id' => $hrBoss->id]);
    $employee = Employee::factory()->forCompany($this->company)->reportingTo($hrBossEmployee)->create();
    $otherHr = userWith(Role::HrAdmin, $this->company);

    $leave = fileLeave($otherHr, $employee);

    expect($leave->workflow->steps)->toHaveCount(1)
        ->and($leave->workflow->steps[0]->approver_user_id)->toBe($hrBoss->id);

    // A different HR Admin cannot; the one the employee reports to can.
    $this->actingAs($otherHr)->put(route('leave-requests.decision', $leave), ['decision' => 'approve'])->assertForbidden();
    $this->actingAs($hrBoss)->put(route('leave-requests.decision', $leave), ['decision' => 'approve'])->assertRedirect();

    expect($leave->refresh()->workflow->stage())->toBe(LeaveRequestStage::Approved);
});

it('requires a reason on a rejection', function () {
    [$employee, $supervisorUser] = employeeUnderSupervisor($this->company);
    $leave = fileLeave(userWith(Role::HrAdmin, $this->company), $employee);

    $this->actingAs($supervisorUser)
        ->from(route('leave-requests.index'))
        ->put(route('leave-requests.decision', $leave), ['decision' => 'reject'])
        ->assertSessionHasErrors('note');

    expect($leave->refresh()->workflow->stage())->toBe(LeaveRequestStage::Submitted);
});

it('records a rejection with its reason', function () {
    [$employee, $supervisorUser] = employeeUnderSupervisor($this->company);
    $leave = fileLeave(userWith(Role::HrAdmin, $this->company), $employee);

    $this->actingAs($supervisorUser)
        ->put(route('leave-requests.decision', $leave), [
            'decision' => 'reject',
            'note' => 'Tim sedang kekurangan orang',
        ])
        ->assertRedirect();

    $leave->refresh();

    expect($leave->workflow->stage())->toBe(LeaveRequestStage::Rejected)
        ->and($leave->workflow->steps[0]->status)->toBe(WorkflowStepStatus::Rejected)
        ->and($leave->workflow->steps[0]->note)->toBe('Tim sedang kekurangan orang');
});

it('refuses a second decision on a settled request', function () {
    $hr = userWith(Role::HrAdmin, $this->company);
    $employee = Employee::factory()->forCompany($this->company)->create();
    $leave = fileLeave($hr, $employee);

    $this->actingAs($hr)->put(route('leave-requests.decision', $leave), ['decision' => 'approve']);

    $this->actingAs($hr)
        ->put(route('leave-requests.decision', $leave), ['decision' => 'approve'])
        ->assertForbidden();
});

it('stops a plain employee filing leave for someone else', function () {
    $employee = Employee::factory()->forCompany($this->company)->create();
    $self = Employee::factory()->forCompany($this->company)->create();
    $user = userWith(Role::Employee, $this->company);
    $self->update(['user_id' => $user->id]);

    $this->actingAs($user)
        ->from(route('leave-requests.create'))
        ->post(route('leave-requests.store'), [
            'employee_id' => $employee->id,
            'leave_type' => 'annual',
            'start_on' => '2026-11-02',
            'end_on' => '2026-11-04',
        ])
        ->assertSessionHasErrors('employee_id');

    expect(LeaveRequest::count())->toBe(0);
});

it('lets an employee file their own leave', function () {
    $user = userWith(Role::Employee, $this->company);
    $self = Employee::factory()->forCompany($this->company)->create(['user_id' => $user->id]);

    $leave = fileLeave($user, $self);

    expect($leave->employee_id)->toBe($self->id);
});

it('hides another company requests from the queue', function () {
    $other = Company::factory()->create();
    $otherEmployee = Employee::factory()->forCompany($other)->create();
    LeaveRequest::factory()->forEmployee($otherEmployee)->create();

    $mine = Employee::factory()->forCompany($this->company)->create();
    LeaveRequest::factory()->forEmployee($mine)->create();

    $this->actingAs(userWith(Role::HrAdmin, $this->company))
        ->get(route('leave-requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('requests', 1)
            ->where('requests.0.employee', $mine->full_name)
        );
});

it('refuses leave filed for an employee in another company', function () {
    $other = Company::factory()->create();
    $foreign = Employee::factory()->forCompany($other)->create();

    $this->actingAs(userWith(Role::HrAdmin, $this->company))
        ->from(route('leave-requests.create'))
        ->post(route('leave-requests.store'), [
            'employee_id' => $foreign->id,
            'leave_type' => 'annual',
            'start_on' => '2026-11-02',
            'end_on' => '2026-11-04',
        ])
        // CompanyScope means the employee simply does not exist for this user.
        ->assertSessionHasErrors('employee_id');

    expect(LeaveRequest::acrossCompanies()->count())->toBe(0);
});

it('rejects an end date before the start date', function () {
    $employee = Employee::factory()->forCompany($this->company)->create();

    $this->actingAs(userWith(Role::HrAdmin, $this->company))
        ->from(route('leave-requests.create'))
        ->post(route('leave-requests.store'), [
            'employee_id' => $employee->id,
            'leave_type' => 'annual',
            'start_on' => '2026-11-04',
            'end_on' => '2026-11-02',
        ])
        ->assertSessionHasErrors('end_on');
});

it('shows the employee directory scoped to the active company', function () {
    Employee::factory()->count(3)->forCompany($this->company)->create();
    Employee::factory()->count(2)->forCompany(Company::factory()->create())->create();

    $this->actingAs(userWith(Role::HrAdmin, $this->company))
        ->get(route('employees.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('employees', 3));
});
