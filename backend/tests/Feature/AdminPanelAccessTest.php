<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function panelUser(Role $role, ?Company $company = null): User
{
    $user = User::factory()->create(['company_id' => $company?->id]);
    $user->assignRole($role->value);

    return $user;
}

it('lets parent-wide system and HR admins open the admin panel', function (Role $role) {
    $this->actingAs(panelUser($role))->get('/admin')->assertOk();
})->with([Role::SystemAdmin, Role::HrAdmin]);

it('keeps a company-pinned admin out of the panel', function () {
    $this->actingAs(panelUser(Role::HrAdmin, Company::factory()->create()))
        ->get('/admin')
        ->assertForbidden();
});

it('keeps roles without the permission out of the panel', function (Role $role) {
    $this->actingAs(panelUser($role))->get('/admin')->assertForbidden();
})->with([Role::Management, Role::Supervisor, Role::Employee]);

it('sends guests to the panel login', function () {
    $this->get('/admin')->assertRedirect(Filament::getPanel('admin')->getLoginUrl());
});

it('follows a permission granted to a role in the database', function () {
    $user = panelUser(Role::Management);

    $this->actingAs($user)->get('/admin')->assertForbidden();

    Spatie\Permission\Models\Role::findByName(Role::Management->value)
        ->givePermissionTo(Permission::AccessAdminPanel->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($user->fresh())->get('/admin')->assertOk();
});
