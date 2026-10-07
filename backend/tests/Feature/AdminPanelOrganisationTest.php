<?php

use App\Enums\Role;
use App\Filament\Resources\Departments\Pages\CreateDepartment;
use App\Filament\Resources\Positions\Pages\CreatePosition;
use App\Http\Middleware\SetActiveCompany;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/**
 * The admin panel's Departments, Positions and Audit log screens (CORE-ORG-001/002, CORE-RBA-007).
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->a = Company::factory()->create();
    $this->b = Company::factory()->create();
});

function panelAdmin(Role $role): User
{
    $user = User::factory()->create(['company_id' => null]);
    $user->assignRole($role->value);

    return $user;
}

it('creates a department in the chosen company while viewing every company', function () {
    $this->actingAs(panelAdmin(Role::HrAdmin));

    Livewire::test(CreateDepartment::class)
        ->fillForm(['company_id' => $this->b->id, 'name' => 'Gudang'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Department::acrossCompanies()->where('name', 'Gudang')->value('company_id'))->toBe($this->b->id);
});

it('allows the same department name in two companies but not twice in one', function () {
    Department::factory()->forCompany($this->a)->create(['name' => 'Gudang']);
    $this->actingAs(panelAdmin(Role::HrAdmin));

    Livewire::test(CreateDepartment::class)
        ->fillForm(['company_id' => $this->b->id, 'name' => 'Gudang'])
        ->call('create')
        ->assertHasNoFormErrors();

    Livewire::test(CreateDepartment::class)
        ->fillForm(['company_id' => $this->a->id, 'name' => 'Gudang'])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique']);
});

it('refuses a position whose department belongs to another company', function () {
    $foreign = Department::factory()->forCompany($this->b)->create();
    $this->actingAs(panelAdmin(Role::HrAdmin));

    Livewire::test(CreatePosition::class)
        ->fillForm(['company_id' => $this->a->id, 'department_id' => $foreign->id, 'name' => 'Kepala'])
        ->call('create')
        ->assertHasFormErrors(['department_id']);

    expect(Position::acrossCompanies()->where('name', 'Kepala')->exists())->toBeFalse();
});

it('creates a position under a department of the same company', function () {
    $department = Department::factory()->forCompany($this->a)->create();
    $this->actingAs(panelAdmin(Role::HrAdmin));

    Livewire::test(CreatePosition::class)
        ->fillForm(['company_id' => $this->a->id, 'department_id' => $department->id, 'name' => 'Staf'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Position::acrossCompanies()->firstWhere('name', 'Staf')->company_id)->toBe($this->a->id);
});

it('lists only the departments of the selected company', function () {
    $mine = Department::factory()->forCompany($this->a)->create(['name' => 'Milik A']);
    Department::factory()->forCompany($this->b)->create(['name' => 'Milik B']);

    $this->actingAs(panelAdmin(Role::HrAdmin))
        ->withSession([SetActiveCompany::SESSION_KEY => (string) $this->a->id])
        ->get('/admin/departments')
        ->assertOk()
        ->assertSee('Milik A')
        ->assertDontSee('Milik B');

    expect($mine->exists)->toBeTrue();
});

it('re-applies the company scope on Livewire updates, not only on the first page load', function () {
    // Table search, sorting and pagination are Livewire requests that skip the panel's normal
    // middleware unless it is registered as persistent.
    $this->actingAs(panelAdmin(Role::HrAdmin))->get('/admin')->assertOk();

    expect(Livewire::getPersistentMiddleware())->toContain(SetActiveCompany::class);
});

it('will not delete a department that still has employees', function () {
    $department = Department::factory()->forCompany($this->a)->create();
    $admin = panelAdmin(Role::HrAdmin);

    expect($admin->can('delete', $department))->toBeTrue();

    Employee::factory()->inDepartment($department)->create();

    expect($admin->can('delete', $department))->toBeFalse();
});

it('shows the audit log to a system admin only', function () {
    activity('roles')->log('Role granted: supervisor');

    $this->actingAs(panelAdmin(Role::SystemAdmin))->get('/admin/activity-logs')->assertOk();
    $this->actingAs(panelAdmin(Role::HrAdmin))->get('/admin/activity-logs')->assertForbidden();

    expect(Activity::count())->toBeGreaterThan(0);
});
