<?php

use App\Enums\EmploymentStatus;
use App\Enums\Role;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/**
 * The employee directory and dossier (EMP-DIR, EMP-ADD, EMP-PRF, EMP-DET).
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->hr = User::factory()->create(['company_id' => $this->company->id]);
    $this->hr->assignRole(Role::HrAdmin->value);
});

it('searches by name and by employee number', function () {
    Employee::factory()->forCompany($this->company)->create([
        'full_name' => 'Budi Santoso', 'employee_number' => 'EMP-0001',
    ]);
    Employee::factory()->forCompany($this->company)->create([
        'full_name' => 'Siti Aminah', 'employee_number' => 'EMP-0002',
    ]);

    $this->actingAs($this->hr)
        ->get(route('employees.index', ['search' => 'Budi']))
        ->assertInertia(fn (Assert $page) => $page->has('employees', 1)
            ->where('employees.0.name', 'Budi Santoso'));

    $this->actingAs($this->hr)
        ->get(route('employees.index', ['search' => 'EMP-0002']))
        ->assertInertia(fn (Assert $page) => $page->has('employees', 1)
            ->where('employees.0.name', 'Siti Aminah'));
});

it('names the company each employee belongs to', function () {
    $this->company->update(['name' => 'Maunarific']);
    Employee::factory()->forCompany($this->company)->create();

    $this->actingAs($this->hr)
        ->get(route('employees.index'))
        ->assertInertia(fn (Assert $page) => $page->where('employees.0.company', 'Maunarific'));
});

it('filters by department and by status', function () {
    $warehouse = Department::factory()->forCompany($this->company)->create();
    Employee::factory()->inDepartment($warehouse)->create();
    Employee::factory()->forCompany($this->company)->create();
    Employee::factory()->forCompany($this->company)->inactive()->create();

    $this->actingAs($this->hr)
        ->get(route('employees.index', ['departments' => [$warehouse->name]]))
        ->assertInertia(fn (Assert $page) => $page->has('employees', 1));

    $this->actingAs($this->hr)
        ->get(route('employees.index', ['statuses' => [EmploymentStatus::Inactive->value]]))
        ->assertInertia(fn (Assert $page) => $page->has('employees', 1));
});

it('lists a department once however many companies have it, and filters across them', function () {
    $other = Company::factory()->create();
    $group = User::factory()->create(['company_id' => null]);
    $group->assignRole(Role::HrAdmin->value);

    $here = Department::factory()->forCompany($this->company)->create(['name' => 'Gudang']);
    $there = Department::factory()->forCompany($other)->create(['name' => 'Gudang']);
    Department::factory()->forCompany($other)->create(['name' => 'Finance']);
    Employee::factory()->inDepartment($here)->create();
    Employee::factory()->inDepartment($there)->create();
    Employee::factory()->forCompany($other)->create();

    $this->actingAs($group)
        ->get(route('employees.index', ['departments' => ['Gudang']]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('departments', ['Finance', 'Gudang'])
            ->has('employees', 2));
});

it('reports the filtered count against the company total', function () {
    Employee::factory()->count(4)->forCompany($this->company)->create([
        'full_name' => 'Budi Santoso',
    ]);
    Employee::factory()->forCompany($this->company)->create(['full_name' => 'Siti Aminah']);

    $this->actingAs($this->hr)
        ->get(route('employees.index', ['search' => 'Budi']))
        ->assertInertia(fn (Assert $page) => $page->has('employees', 4)->where('total', 5));
});

it('creates an employee in the active company', function () {
    $this->actingAs($this->hr)
        ->post(route('employees.store'), [
            'employee_number' => 'EMP-9001',
            'national_id' => '3174000000009001',
            'full_name' => 'Karyawan Baru',
            'employment_status' => EmploymentStatus::Active->value,
            'base_salary_idr' => 7500000,
        ])
        ->assertRedirect();

    $employee = Employee::firstWhere('employee_number', 'EMP-9001');

    expect($employee)->not->toBeNull()
        // Filled by BelongsToCompany from the active company (EMP-ADD-004).
        ->and($employee->company_id)->toBe($this->company->id)
        ->and($employee->base_salary_idr)->toBe(7500000);
});

it('keeps employee numbers unique per company, not group-wide', function () {
    Employee::factory()->forCompany($this->company)->create(['employee_number' => 'EMP-0001']);

    // The same number in a different company is fine.
    $other = Company::factory()->create();
    Employee::factory()->forCompany($other)->create(['employee_number' => 'EMP-0001']);

    $this->actingAs($this->hr)
        ->from(route('employees.create'))
        ->post(route('employees.store'), [
            'employee_number' => 'EMP-0001',
            'full_name' => 'Duplikat',
            'employment_status' => EmploymentStatus::Active->value,
        ])
        ->assertSessionHasErrors('employee_number');
});

it('refuses a reporting line that points at itself', function () {
    $employee = Employee::factory()->forCompany($this->company)->create();

    $this->actingAs($this->hr)
        ->from(route('employees.edit', $employee))
        ->put(route('employees.update', $employee), [
            'employee_number' => $employee->employee_number,
            'full_name' => $employee->full_name,
            'employment_status' => EmploymentStatus::Active->value,
            'manager_id' => $employee->id,
        ])
        ->assertSessionHasErrors('manager_id');
});

it('audits a profile edit', function () {
    $employee = Employee::factory()->forCompany($this->company)->create();

    $this->actingAs($this->hr)->put(route('employees.update', $employee), [
        'employee_number' => $employee->employee_number,
        'national_id' => $employee->national_id,
        'full_name' => 'Nama Diperbarui',
        'employment_status' => EmploymentStatus::Active->value,
    ])->assertRedirect();

    $activity = Activity::query()
        ->where('subject_type', $employee->getMorphClass())
        ->where('subject_id', $employee->id)
        ->latest('id')
        ->first();

    expect($activity->attribute_changes['attributes']['full_name'])->toBe('Nama Diperbarui');
});

it('refuses a supervisor the create and edit actions', function () {
    $supervisor = User::factory()->create(['company_id' => $this->company->id]);
    $supervisor->assignRole(Role::Supervisor->value);
    $boss = Employee::factory()->forCompany($this->company)->create(['user_id' => $supervisor->id]);
    $employee = Employee::factory()->forCompany($this->company)->create(['manager_id' => $boss->id]);

    $this->actingAs($supervisor)->get(route('employees.create'))->assertForbidden();
    $this->actingAs($supervisor)->get(route('employees.edit', $employee))->assertForbidden();
    // Reading is still allowed.
    $this->actingAs($supervisor)->get(route('employees.show', $employee))->assertOk();
});

it('shows the dossier groups on the profile', function () {
    $employee = Employee::factory()->forCompany($this->company)->create([
        'bank_name' => 'BCA',
        'base_salary_idr' => 9000000,
        'blood_type' => 'O',
    ]);

    $this->actingAs($this->hr)
        ->get(route('employees.show', $employee))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('employee.bank.bank_name', 'BCA')
            ->where('employee.payroll.base_salary_idr', 9000000)
            ->where('employee.health.blood_type', 'O')
            ->where('canUpdate', true)
            ->has('requests')
            ->where('leaveTakenThisYear', 0)
        );
});

it('hides an employee from another company', function () {
    $foreign = Employee::factory()->forCompany(Company::factory()->create())->create();

    $this->actingAs($this->hr)
        ->get(route('employees.show', $foreign))
        ->assertNotFound();
});

it('offers positions and managers scoped to the company', function () {
    $department = Department::factory()->forCompany($this->company)->create();
    Position::factory()->forDepartment($department)->create();
    Employee::factory()->forCompany($this->company)->create();

    $otherDepartment = Department::factory()->forCompany(Company::factory()->create())->create();
    Position::factory()->forDepartment($otherDepartment)->create();

    $this->actingAs($this->hr)
        ->get(route('employees.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('positions', 1)
            ->has('managers', 1)
            ->has('departments', 1)
        );
});

describe('adding an employee while every company is being viewed', function () {
    beforeEach(function () {
        // A group-level HR Admin has no company of their own, so the view starts on all companies and none is active.
        $this->admin = User::factory()->create(['company_id' => null]);
        $this->admin->assignRole(Role::HrAdmin->value);
    });

    it('offers the companies to choose from', function () {
        $this->actingAs($this->admin)
            ->get(route('employees.create'))
            ->assertInertia(fn (Assert $page) => $page->has('companies', Company::count()));

        $this->actingAs($this->hr)
            ->get(route('employees.create'))
            ->assertInertia(fn (Assert $page) => $page->where('companies', null));
    });

    it('files the employee under the company that was chosen', function () {
        $this->actingAs($this->admin)
            ->post(route('employees.store'), [
                'company_id' => $this->company->id,
                'employee_number' => 'EMP-7001',
                'national_id' => '3174000000007001',
                'full_name' => 'Karyawan Pilihan',
                'employment_status' => EmploymentStatus::Active->value,
            ])
            ->assertRedirect();

        expect(Employee::acrossCompanies()->firstWhere('employee_number', 'EMP-7001')->company_id)
            ->toBe($this->company->id);
    });

    it('asks for a company on the field instead of failing without a message', function () {
        $this->actingAs($this->admin)
            ->from(route('employees.create'))
            ->post(route('employees.store'), [
                'employee_number' => 'EMP-7002',
                'national_id' => '3174000000007002',
                'full_name' => 'Tanpa Perusahaan',
                'employment_status' => EmploymentStatus::Active->value,
            ])
            ->assertSessionHasErrors('company_id');

        expect(Employee::acrossCompanies()->where('employee_number', 'EMP-7002')->exists())->toBeFalse();
    });

    it('refuses a department from a different company than the one chosen', function () {
        $foreign = Department::factory()->forCompany(Company::factory()->create())->create();

        $this->actingAs($this->admin)
            ->from(route('employees.create'))
            ->post(route('employees.store'), [
                'company_id' => $this->company->id,
                'employee_number' => 'EMP-7003',
                'national_id' => '3174000000007003',
                'full_name' => 'Salah Departemen',
                'employment_status' => EmploymentStatus::Active->value,
                'department_id' => $foreign->id,
            ])
            ->assertSessionHasErrors('department_id');
    });

    it('checks the employee number against the chosen company', function () {
        Employee::factory()->forCompany($this->company)->create(['employee_number' => 'EMP-0001']);

        $this->actingAs($this->admin)
            ->from(route('employees.create'))
            ->post(route('employees.store'), [
                'company_id' => $this->company->id,
                'employee_number' => 'EMP-0001',
                'national_id' => '3174000000007004',
                'full_name' => 'Duplikat',
                'employment_status' => EmploymentStatus::Active->value,
            ])
            ->assertSessionHasErrors('employee_number');
    });
});

it('carries a change of the employee name over to their account', function () {
    $account = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Nama Lama']);
    $employee = Employee::factory()->forCompany($this->company)->create(['user_id' => $account->id, 'full_name' => 'Nama Lama']);

    $this->actingAs($this->hr)->put(route('employees.update', $employee), [
        'employee_number' => $employee->employee_number,
        'national_id' => $employee->national_id,
        'full_name' => 'Nama Baru',
        'employment_status' => EmploymentStatus::Active->value,
    ])->assertRedirect();

    expect($account->refresh()->name)->toBe('Nama Baru');
});

it('leaves the account alone when something other than the name changes', function () {
    $account = User::factory()->create(['company_id' => $this->company->id, 'name' => 'Nama Akun']);
    $employee = Employee::factory()->forCompany($this->company)->create(['user_id' => $account->id, 'full_name' => 'Nama Karyawan']);

    $employee->update(['phone' => '0812-0000-0000']);

    expect($account->refresh()->name)->toBe('Nama Akun');
});
