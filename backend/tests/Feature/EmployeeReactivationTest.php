<?php

use App\Enums\EmploymentStatus;
use App\Enums\Role;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/**
 * A closed profile is only ever closed by offboarding, and only brought back by reactivating it (EMP-ADD-006).
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->hr = User::factory()->create(['company_id' => $this->company->id]);
    $this->hr->assignRole(Role::HrAdmin->value);
});

function closedEmployee(Company $company, bool $withAccount = true): Employee
{
    $user = $withAccount ? User::factory()->create(['company_id' => $company->id, 'is_active' => false]) : null;

    return Employee::factory()->forCompany($company)->create([
        'employment_status' => EmploymentStatus::Inactive,
        'exited_on' => '2026-09-30',
        'user_id' => $user?->id,
    ]);
}

it('brings a closed profile back, clears the exit date and switches the sign-in back on', function () {
    $employee = closedEmployee($this->company);

    $this->actingAs($this->hr)
        ->post(route('employees.reactivate', $employee))
        ->assertRedirect();

    $employee->refresh();

    expect($employee->employment_status)->toBe(EmploymentStatus::Active)
        ->and($employee->exited_on)->toBeNull()
        ->and($employee->user->is_active)->toBeTrue();

    // The profile is editable again.
    expect($this->hr->can('update', $employee))->toBeTrue();
    expect(Activity::where('log_name', 'account')->where('description', 'HR mengaktifkan kembali karyawan')->exists())->toBeTrue();
});

it('leaves an account that is still waiting for HR to activate it as it is', function () {
    $employee = closedEmployee($this->company);
    $employee->user->forceFill(['awaiting_activation' => true])->save();

    $this->actingAs($this->hr)->post(route('employees.reactivate', $employee))->assertRedirect();

    expect($employee->refresh()->user->is_active)->toBeFalse();
});

it('works for a profile that never had an account', function () {
    $employee = closedEmployee($this->company, withAccount: false);

    $this->actingAs($this->hr)->post(route('employees.reactivate', $employee))->assertRedirect();

    expect($employee->refresh()->employment_status)->toBe(EmploymentStatus::Active);
});

it('is refused for a profile that is not closed, for someone without the right, and for your own profile', function () {
    $active = Employee::factory()->forCompany($this->company)->create();
    $this->actingAs($this->hr)->post(route('employees.reactivate', $active))->assertForbidden();

    $supervisor = User::factory()->create(['company_id' => $this->company->id]);
    $supervisor->assignRole(Role::Supervisor->value);
    $this->actingAs($supervisor)->post(route('employees.reactivate', closedEmployee($this->company)))->assertForbidden();

    $own = Employee::factory()->forCompany($this->company)->create([
        'employment_status' => EmploymentStatus::Inactive,
        'user_id' => $this->hr->id,
    ]);
    $this->actingAs($this->hr)->post(route('employees.reactivate', $own))->assertForbidden();
});

it('offers the action on a closed profile only to those who may use it', function () {
    $employee = closedEmployee($this->company);

    $this->actingAs($this->hr)
        ->get(route('employees.show', $employee))
        ->assertInertia(fn (Assert $page) => $page->where('canReactivate', true));

    $active = Employee::factory()->forCompany($this->company)->create();

    $this->actingAs($this->hr)
        ->get(route('employees.show', $active))
        ->assertInertia(fn (Assert $page) => $page->where('canReactivate', false));
});

it('cannot close a profile from the forms, only offer and accept active', function () {
    $this->actingAs($this->hr)
        ->get(route('employees.create'))
        ->assertInertia(fn (Assert $page) => $page->has('employmentStatuses', 1)->where('employmentStatuses.0.value', 'active'));

    $this->actingAs($this->hr)
        ->from(route('employees.create'))
        ->post(route('employees.store'), [
            'employee_number' => 'EMP-5001',
            'national_id' => '3174000000005001',
            'full_name' => 'Tidak Boleh Nonaktif',
            'employment_status' => EmploymentStatus::Inactive->value,
        ])
        ->assertSessionHasErrors('employment_status');

    $employee = Employee::factory()->forCompany($this->company)->create();

    $this->actingAs($this->hr)
        ->from(route('employees.edit', $employee))
        ->put(route('employees.update', $employee), [
            'employee_number' => $employee->employee_number,
            'national_id' => $employee->national_id,
            'full_name' => $employee->full_name,
            'employment_status' => EmploymentStatus::Inactive->value,
        ])
        ->assertSessionHasErrors('employment_status');

    expect($employee->refresh()->employment_status)->toBe(EmploymentStatus::Active);
});
