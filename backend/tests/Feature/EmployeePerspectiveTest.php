<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * What an ordinary employee — no permissions at all — sees of the application.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->assignRole(Role::Employee->value);
    $this->mine = Employee::factory()->forCompany($this->company)->create(['user_id' => $this->user->id]);
});

function submitLeaveFor(Employee $employee, User $filedBy): void
{
    test()->actingAs($filedBy)->post(route('leave-requests.store'), [
        'employee_id' => $employee->id,
        'leave_type' => 'annual',
        'start_on' => now()->addWeek()->toDateString(),
        'end_on' => now()->addWeek()->addDay()->toDateString(),
        'reason' => 'Keperluan keluarga',
    ])->assertRedirect();
}

it('lists only the employee\'s own leave requests, not their colleagues\'', function () {
    $colleagueUser = User::factory()->create(['company_id' => $this->company->id]);
    $colleagueUser->assignRole(Role::Employee->value);
    $colleague = Employee::factory()->forCompany($this->company)->create(['user_id' => $colleagueUser->id]);

    submitLeaveFor($this->mine, $this->user);
    submitLeaveFor($colleague, $colleagueUser);

    $this->actingAs($this->user)->get(route('leave-requests.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('requests', 1)
            ->where('requests.0.employee', $this->mine->full_name));
});

it('still shows HR every request in the company', function () {
    $hr = User::factory()->create(['company_id' => $this->company->id]);
    $hr->assignRole(Role::HrAdmin->value);
    $other = Employee::factory()->forCompany($this->company)->create();

    submitLeaveFor($this->mine, $this->user);
    submitLeaveFor($other, $hr);

    $this->actingAs($hr)->get(route('leave-requests.index'))
        ->assertInertia(fn (Assert $page) => $page->has('requests', 2));
});

it('shows an approver the requests waiting on them even without the employee-view permission', function () {
    // The approver is a plain employee-role account named as the supervisor's login.
    $approver = User::factory()->create(['company_id' => $this->company->id]);
    $approver->assignRole(Role::Employee->value);
    $approverRecord = Employee::factory()->forCompany($this->company)->create(['user_id' => $approver->id]);
    $this->mine->update(['manager_id' => $approverRecord->id]);

    submitLeaveFor($this->mine, $this->user);

    $this->actingAs($approver)->get(route('leave-requests.index'))
        ->assertInertia(fn (Assert $page) => $page->has('requests', 1));
});

it('tells the sidebar to offer the employee their own profile and not the directory', function () {
    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.canViewEmployees', false)
            ->where('auth.employeeId', $this->mine->id)
            ->where('auth.canAccessAdminPanel', false));
});

it('offers HR the directory', function () {
    $hr = User::factory()->create(['company_id' => $this->company->id]);
    $hr->assignRole(Role::HrAdmin->value);

    $this->actingAs($hr)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canViewEmployees', true));
});

it('lets an employee open their own profile but not the directory or someone else\'s', function () {
    $other = Employee::factory()->forCompany($this->company)->create();

    $this->actingAs($this->user)->get(route('employees.show', $this->mine))->assertOk();
    $this->actingAs($this->user)->get(route('employees.index'))->assertForbidden();
    $this->actingAs($this->user)->get(route('employees.show', $other))->assertForbidden();
});

it('cannot edit their own profile', function () {
    $this->actingAs($this->user)->get(route('employees.edit', $this->mine))->assertForbidden();
});

it('lets an employee read the reviewer\'s note on their own rejected request from their profile', function () {
    $hr = User::factory()->create(['company_id' => $this->company->id]);
    $hr->assignRole(Role::HrAdmin->value);

    submitLeaveFor($this->mine, $this->user);
    $leave = LeaveRequest::query()->latest('id')->firstOrFail();

    $this->actingAs($hr)->put(route('leave-requests.decision', $leave), [
        'decision' => 'reject', 'note' => 'Bertabrakan dengan audit akhir tahun',
    ])->assertRedirect();

    $this->actingAs($this->user)->get(route('employees.show', $this->mine))
        ->assertInertia(fn (Assert $page) => $page
            ->where('requests.0.reason', 'Keperluan keluarga')
            ->where('requests.0.steps.0.status', 'rejected')
            ->where('requests.0.steps.0.note', 'Bertabrakan dengan audit akhir tahun')
            ->where('requests.0.steps.0.decidedBy', $hr->name));

    $this->actingAs($this->user)->getJson(route('employees.summary', $this->mine))
        ->assertOk()
        ->assertJsonPath('requests.0.steps.0.note', 'Bertabrakan dengan audit akhir tahun');
});
