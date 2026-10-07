<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Filament\Resources\Companies\Pages\CreateCompany;
use App\Filament\Resources\Companies\Pages\EditCompany;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * The admin panel's Companies, Users and Roles screens (CORE-ADM, CORE-USR, CORE-RBA).
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function adminFor(Role $role, ?Company $company = null): User
{
    $user = User::factory()->create(['company_id' => $company?->id]);
    $user->assignRole($role->value);

    return $user;
}

it('lets a system admin create and deactivate a company', function () {
    $this->actingAs(adminFor(Role::SystemAdmin));

    Livewire::test(CreateCompany::class)
        ->fillForm(['code' => 'XYZ', 'name' => 'Xyz Corp', 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $company = Company::where('code', 'XYZ')->firstOrFail();

    Livewire::test(EditCompany::class, ['record' => $company->getKey()])
        ->fillForm(['is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($company->fresh()->is_active)->toBeFalse();
});

it('rejects a duplicate company code', function () {
    Company::factory()->create(['code' => 'DUP']);
    $this->actingAs(adminFor(Role::SystemAdmin));

    Livewire::test(CreateCompany::class)
        ->fillForm(['code' => 'DUP', 'name' => 'Another'])
        ->call('create')
        ->assertHasFormErrors(['code' => 'unique']);
});

it('hides companies, users and roles from an HR admin who lacks the manage permissions', function () {
    $this->actingAs(adminFor(Role::HrAdmin));

    $this->get('/admin/companies')->assertForbidden();
    $this->get('/admin/users')->assertForbidden();
    $this->get('/admin/roles')->assertForbidden();
});

it('creates a user with a role, verified, and audits the role grant', function () {
    $company = Company::factory()->create();
    $this->actingAs(adminFor(Role::SystemAdmin));

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Sari',
            'email' => 'sari@example.test',
            'password' => 'a-long-enough-pass',
            'company_id' => $company->id,
            'role_names' => [Role::Supervisor->value],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'sari@example.test')->firstOrFail();

    expect($user->company_id)->toBe($company->id)
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->isA(Role::Supervisor))->toBeTrue()
        ->and(Activity::where('log_name', 'roles')->where('description', 'like', 'Role granted%')->exists())->toBeTrue();
});

it('does not change roles on the account you are signed in as', function () {
    $admin = adminFor(Role::SystemAdmin);
    $this->actingAs($admin);

    Livewire::test(EditUser::class, ['record' => $admin->getKey()])
        ->fillForm(['name' => 'Renamed', 'role_names' => [Role::Employee->value]])
        ->call('save');

    expect($admin->fresh()->name)->toBe('Renamed')
        ->and($admin->fresh()->isA(Role::SystemAdmin))->toBeTrue()
        ->and($admin->fresh()->isA(Role::Employee))->toBeFalse();
});

it('keeps the current password when the field is left blank on edit', function () {
    $this->actingAs(adminFor(Role::SystemAdmin));
    $target = User::factory()->create();
    $hash = $target->password;

    Livewire::test(EditUser::class, ['record' => $target->getKey()])
        ->fillForm(['name' => 'Changed', 'password' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($target->fresh()->password)->toBe($hash);
});

it('lets an admin grant a role the admin-panel permission and records the change', function () {
    $this->actingAs(adminFor(Role::SystemAdmin));
    $management = RoleModel::findByName(Role::Management->value);

    Livewire::test(EditRole::class, ['record' => $management->getKey()])
        ->fillForm(['permission_names' => [Permission::ViewEmployees->value, Permission::AccessAdminPanel->value]])
        ->call('save');

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($management->fresh()->hasPermissionTo(Permission::AccessAdminPanel->value))->toBeTrue()
        ->and(Activity::where('log_name', 'roles')->where('description', 'like', 'Permissions changed%')->exists())->toBeTrue();
});

it('will not let the system admin role lose the permissions that keep the panel reachable', function () {
    $this->actingAs(adminFor(Role::SystemAdmin));
    $role = RoleModel::findByName(Role::SystemAdmin->value);

    Livewire::test(EditRole::class, ['record' => $role->getKey()])
        ->fillForm(['permission_names' => []])
        ->call('save');

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $role = $role->fresh();

    expect($role->hasPermissionTo(Permission::ManageUsers->value))->toBeTrue()
        ->and($role->hasPermissionTo(Permission::AccessAdminPanel->value))->toBeTrue();
});
