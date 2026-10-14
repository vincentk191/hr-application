<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RoleSeeder;

/**
 * Who may see and change a dossier record (CORE-RBA-003). These are the workbook's
 * "Permission" test type: not what the UI shows, but what the server allows.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function userWithRole(Role $role, ?Company $company = null): User
{
    $user = User::factory()->create(['company_id' => $company?->id]);
    $user->assignRole($role->value);

    return $user;
}

it('lets HR view and update a record in their own company', function () {
    $company = Company::factory()->create();
    $hr = userWithRole(Role::HrAdmin, $company);
    $employee = Employee::factory()->forCompany($company)->create();

    expect($hr->can('view', $employee))->toBeTrue()
        ->and($hr->can('update', $employee))->toBeTrue()
        ->and($hr->can('create', Employee::class))->toBeTrue();
});

it('refuses a company-pinned HR account a record in another company', function () {
    $own = Company::factory()->create();
    $other = Company::factory()->create();
    $hr = userWithRole(Role::HrAdmin, $own);

    // Fetched through the bypass, as a console command or a parent-wide report would.
    $foreign = Employee::factory()->forCompany($other)->create();

    expect($hr->can('view', $foreign))->toBeFalse()
        ->and($hr->can('update', $foreign))->toBeFalse();
});

it('lets a parent-wide HR account reach any company', function () {
    $hr = userWithRole(Role::HrAdmin);
    $employee = Employee::factory()->forCompany(Company::factory()->create())->create();

    expect($hr->can('view', $employee))->toBeTrue()
        ->and($hr->can('update', $employee))->toBeTrue();
});

it('refuses a supervisor any change to a record', function () {
    $company = Company::factory()->create();
    $supervisor = userWithRole(Role::Supervisor, $company);
    $boss = Employee::factory()->forCompany($company)->create(['user_id' => $supervisor->id]);
    $employee = Employee::factory()->forCompany($company)->create(['manager_id' => $boss->id]);

    expect($supervisor->can('view', $employee))->toBeTrue()
        ->and($supervisor->can('update', $employee))->toBeFalse()
        ->and($supervisor->can('create', Employee::class))->toBeFalse();
});

it('lets an employee see their own record without a view permission', function () {
    $company = Company::factory()->create();
    $user = userWithRole(Role::Employee, $company);
    $own = Employee::factory()->forCompany($company)->create(['user_id' => $user->id]);
    $colleague = Employee::factory()->forCompany($company)->create();

    expect($user->can('view', $own))->toBeTrue()
        ->and($user->can('view', $colleague))->toBeFalse()
        ->and($user->can('update', $own))->toBeFalse();
});

it('locks a record once its offboarding has closed', function () {
    $company = Company::factory()->create();
    $hr = userWithRole(Role::HrAdmin, $company);
    $offboarded = Employee::factory()->forCompany($company)->inactive()->create();

    // EMP-ADD-006 / OFF-SIG-009: inactive records are read-only.
    expect($hr->can('view', $offboarded))->toBeTrue()
        ->and($hr->can('update', $offboarded))->toBeFalse();
});

it('never allows a dossier record to be deleted', function () {
    $company = Company::factory()->create();
    $employee = Employee::factory()->forCompany($company)->create();

    foreach ([Role::SystemAdmin, Role::HrAdmin, Role::Management] as $role) {
        expect(userWithRole($role, $company)->can('delete', $employee))->toBeFalse();
    }
});

it('records who changed a profile field', function () {
    $employee = Employee::factory()->forCompany(Company::factory()->create())->create();

    $employee->update(['full_name' => 'Nama Baru']);

    $activity = $employee->activitiesAsSubject()->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->attribute_changes['attributes']['full_name'])->toBe('Nama Baru');
});

it('does not log an update that changed nothing', function () {
    $employee = Employee::factory()->forCompany(Company::factory()->create())->create();
    $before = $employee->activitiesAsSubject()->count();

    $employee->update(['full_name' => $employee->full_name]);

    expect($employee->activitiesAsSubject()->count())->toBe($before);
});
