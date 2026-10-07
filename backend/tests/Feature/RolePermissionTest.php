<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * The role hierarchy of context.md §9.2 and the permissions each role holds (CORE-RBA-001/002).
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('seeds every role and permission in the enums', function () {
    expect(RoleModel::pluck('name')->sort()->values()->all())
        ->toBe(collect(Role::cases())->map->value->sort()->values()->all())
        ->and(PermissionModel::pluck('name')->sort()->values()->all())
        ->toBe(collect(Permission::cases())->map->value->sort()->values()->all());
});

it('is idempotent', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(RoleSeeder::class);

    expect(RoleModel::count())->toBe(count(Role::cases()))
        ->and(PermissionModel::count())->toBe(count(Permission::cases()));
});

it('leaves an existing role alone when the seeder runs again', function () {
    $supervisor = RoleModel::findByName(Role::Supervisor->value);
    $supervisor->givePermissionTo(Permission::ManageUsers->value);

    // Permissions are managed from the admin panel once a role exists; the seeder only
    // creates what is missing, so an administrator's change survives a re-run.
    $this->seed(RoleSeeder::class);

    expect($supervisor->fresh()->hasPermissionTo(Permission::ManageUsers->value))->toBeTrue();
});

it('gives a newly created role its enum defaults', function () {
    RoleModel::findByName(Role::Supervisor->value)->delete();

    $this->seed(RoleSeeder::class);

    expect(RoleModel::findByName(Role::Supervisor->value)->hasPermissionTo(Permission::ViewEmployees->value))->toBeTrue();
});

it('gives the System Admin account administration but not HR process work', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SystemAdmin->value);

    expect($user->can(Permission::ManageUsers->value))->toBeTrue()
        ->and($user->can(Permission::ManageCompanies->value))->toBeTrue()
        // Administering accounts is not the same as administering the dossier (§9.2).
        ->and($user->can(Permission::CreateEmployees->value))->toBeFalse()
        ->and($user->can(Permission::UpdateEmployees->value))->toBeFalse();
});

it('gives HR the dossier but not account administration', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::HrAdmin->value);

    expect($user->can(Permission::CreateEmployees->value))->toBeTrue()
        ->and($user->can(Permission::UpdateEmployees->value))->toBeTrue()
        ->and($user->can(Permission::ManageOrganisation->value))->toBeTrue()
        ->and($user->can(Permission::ManageUsers->value))->toBeFalse()
        ->and($user->can(Permission::ManageCompanies->value))->toBeFalse();
});

it('gives a supervisor and management read access only', function (Role $role) {
    $user = User::factory()->create();
    $user->assignRole($role->value);

    expect($user->can(Permission::ViewEmployees->value))->toBeTrue()
        ->and($user->can(Permission::UpdateEmployees->value))->toBeFalse()
        ->and($user->can(Permission::CreateEmployees->value))->toBeFalse()
        ->and($user->can(Permission::ManageUsers->value))->toBeFalse();
})->with([
    'supervisor' => Role::Supervisor,
    'management' => Role::Management,
]);

it('gives a plain employee no administrative permission at all', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::Employee->value);

    foreach (Permission::cases() as $permission) {
        expect($user->can($permission->value))->toBeFalse();
    }
});

it('checks a role through the typed helper', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::HrAdmin->value);

    expect($user->isA(Role::HrAdmin))->toBeTrue()
        ->and($user->isA(Role::SystemAdmin))->toBeFalse();
});
