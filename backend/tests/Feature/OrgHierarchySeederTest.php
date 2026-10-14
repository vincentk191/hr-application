<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\OrgHierarchySeeder;
use Database\Seeders\RoleSeeder;

/**
 * The sample org line the dev database is filled with: one Management per company,
 * Supervisors under it, everyone else under a Supervisor.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->companies = collect(['CMP-A', 'CMP-B', 'CMP-C'])
        ->map(fn (string $code): Company => Company::factory()->create(['code' => $code]));

    // Staff a company already has before the seeder runs, as in the dev database.
    Employee::factory()->count(5)->forCompany($this->companies[0])->create();

    // A department from before, headed by an ordinary employee, which has to be re-headed.
    $existing = Employee::factory()->forCompany($this->companies[0])->create();
    Department::factory()->forCompany($this->companies[0])->create(['supervisor_employee_id' => $existing->id]);
});

function employeesOf(Company $company)
{
    return Employee::acrossCompanies()->where('company_id', $company->id)->with('user.roles')->get();
}

it('gives each company exactly one management employee with no superior', function () {
    $this->seed(OrgHierarchySeeder::class);

    foreach ($this->companies as $company) {
        $management = employeesOf($company)->filter(fn (Employee $e): bool => $e->user?->hasRole(Role::Management->value) === true);

        expect($management)->toHaveCount(1)
            ->and($management->first()->manager_id)->toBeNull();
    }
});

it('makes the known director the management of their company', function () {
    $this->seed(OrgHierarchySeeder::class);

    $brad = Employee::acrossCompanies()->where('full_name', 'Brad Mayert V')->sole();

    expect($brad->company_id)->toBe($this->companies[2]->id)
        ->and($brad->user->hasRole(Role::Management->value))->toBeTrue()
        ->and($brad->manager_id)->toBeNull();
});

it('keeps supervisors to the maximum and has them report to management', function () {
    $this->seed(OrgHierarchySeeder::class);

    foreach ($this->companies as $company) {
        $employees = employeesOf($company);
        $management = $employees->first(fn (Employee $e): bool => $e->user?->hasRole(Role::Management->value) === true);
        $supervisors = $employees->filter(fn (Employee $e): bool => $e->user?->hasRole(Role::Supervisor->value) === true);

        expect($supervisors->count())->toBeGreaterThan(0)->toBeLessThanOrEqual(OrgHierarchySeeder::MAX_SUPERVISORS)
            ->and($supervisors->pluck('manager_id')->unique()->all())->toBe([$management->id]);
    }
});

it('puts every other employee under a supervisor', function () {
    $this->seed(OrgHierarchySeeder::class);

    foreach ($this->companies as $company) {
        $employees = employeesOf($company);
        $supervisorIds = $employees
            ->filter(fn (Employee $e): bool => $e->user?->hasRole(Role::Supervisor->value) === true)
            ->modelKeys();
        $staff = $employees->reject(fn (Employee $e): bool => $e->user?->hasRole(Role::Management->value) === true || in_array($e->id, $supervisorIds, true));

        expect($staff)->not->toBeEmpty()
            ->and($staff->every(fn (Employee $e): bool => in_array($e->manager_id, $supervisorIds, true)))->toBeTrue();
    }
});

it('names a head for every department, management or one of its supervisors', function () {
    $this->seed(OrgHierarchySeeder::class);

    foreach ($this->companies as $company) {
        $employees = employeesOf($company)->keyBy('id');

        Department::acrossCompanies()->where('company_id', $company->id)->get()->each(function (Department $department) use ($employees): void {
            $head = $employees[$department->supervisor_employee_id];

            expect($head->user->hasAnyRole([Role::Management->value, Role::Supervisor->value]))->toBeTrue();
        });
    }
});

it('is safe to run twice', function () {
    $this->seed(OrgHierarchySeeder::class);

    $employees = Employee::acrossCompanies()->count();
    $users = User::count();

    $this->seed(OrgHierarchySeeder::class);

    expect(Employee::acrossCompanies()->count())->toBe($employees)
        ->and(User::count())->toBe($users);
});
