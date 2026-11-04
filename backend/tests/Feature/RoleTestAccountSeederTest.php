<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\RoleTestAccountSeeder;

/**
 * The one-login-per-role accounts the dev database is given for trying permissions by hand.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function seededEmployee(string $email): Employee
{
    return Employee::acrossCompanies()->where('user_id', User::where('email', $email)->firstOrFail()->id)->firstOrFail();
}

it('creates one account per role with the right role', function () {
    $this->seed(RoleTestAccountSeeder::class);

    $roles = [
        'director@hr.test' => Role::Management,
        'manager@hr.test' => Role::Management,
        'supervisor@hr.test' => Role::Supervisor,
        'staff1@hr.test' => Role::Employee,
        'staff2@hr.test' => Role::Employee,
    ];

    foreach ($roles as $email => $role) {
        $user = User::where('email', $email)->firstOrFail();

        expect($user->getRoleNames()->all())->toBe([$role->value])
            ->and(password_verify('password', $user->password))->toBeTrue();
    }

    expect(User::role(Role::SystemAdmin->value)->exists())->toBeFalse();
});

it('puts them in one reporting line under the supervisor, with the director at the top', function () {
    $this->seed(RoleTestAccountSeeder::class);

    $director = seededEmployee('director@hr.test');
    $manager = seededEmployee('manager@hr.test');
    $supervisor = seededEmployee('supervisor@hr.test');

    expect($director->manager_id)->toBeNull()
        ->and($manager->manager_id)->toBe($director->id)
        ->and($supervisor->manager_id)->toBe($manager->id)
        ->and(seededEmployee('staff1@hr.test')->manager_id)->toBe($supervisor->id)
        ->and(seededEmployee('staff2@hr.test')->manager_id)->toBe($supervisor->id)
        ->and(Employee::acrossCompanies()->whereIn('manager_id', [$supervisor->id])->pluck('department_id')->unique())->toHaveCount(1);
});

it('adds Marketing and Sales to every company and keeps an existing Design department', function () {
    $companies = Company::factory()->count(2)->sequence(['code' => 'CMP-A'], ['code' => 'CMP-B'])->create();
    $design = Department::factory()->forCompany($companies[1])->create(['name' => 'Design']);

    $this->seed(RoleTestAccountSeeder::class);

    foreach ($companies as $company) {
        foreach (['Marketing', 'Sales'] as $name) {
            expect(Department::acrossCompanies()->where('company_id', $company->id)->where('name', $name)->count())->toBe(1);
        }
    }

    expect(Department::acrossCompanies()->where('name', 'Design')->count())->toBe(1)
        ->and(seededEmployee('supervisor@hr.test')->department_id)->toBe($design->id)
        ->and(seededEmployee('supervisor@hr.test')->company_id)->toBe($companies[1]->id);
});

it('can be run again without duplicating anything', function () {
    $this->seed(RoleTestAccountSeeder::class);
    $this->seed(RoleTestAccountSeeder::class);

    expect(User::where('email', 'like', '%@hr.test')->count())->toBe(5)
        ->and(Employee::acrossCompanies()->whereIn('user_id', User::pluck('id'))->count())->toBe(5)
        ->and(Department::acrossCompanies()->where('name', 'Sales')->count())->toBe(Company::count());
});

it('runs on a production server, using the companies already there and inventing none', function () {
    $company = Company::factory()->create(['code' => 'REAL-1']);
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('db:seed', ['--class' => RoleTestAccountSeeder::class, '--force' => true])->assertSuccessful();

    expect(Company::count())->toBe(1)
        ->and(seededEmployee('supervisor@hr.test')->company_id)->toBe($company->id)
        ->and(User::where('email', 'like', '%@hr.test')->count())->toBe(5)
        ->and(Department::acrossCompanies()->where('company_id', $company->id)->pluck('name')->sort()->values()->all())
        ->toBe(['Design', 'Marketing', 'Sales']);
});

it('creates nothing on a production server that has no company yet', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('db:seed', ['--class' => RoleTestAccountSeeder::class, '--force' => true])->assertSuccessful();

    expect(Company::count())->toBe(0)
        ->and(User::where('email', 'like', '%@hr.test')->exists())->toBeFalse();
});
