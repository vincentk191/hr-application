<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RoleSeeder;

/**
 * Account and role administration (CORE-USR). `User` is not company-tagged, so none of this
 * is protected by CompanyScope — these checks are the only separation there is.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function account(Role $role, ?Company $company = null): User
{
    $user = User::factory()->create(['company_id' => $company?->id]);
    $user->assignRole($role->value);

    return $user;
}

it('lets a System Admin administer accounts', function () {
    $admin = account(Role::SystemAdmin);
    $target = User::factory()->create();

    expect($admin->can('viewAny', User::class))->toBeTrue()
        ->and($admin->can('create', User::class))->toBeTrue()
        ->and($admin->can('update', $target))->toBeTrue()
        ->and($admin->can('assignRoles', $target))->toBeTrue();
});

it('refuses HR account administration', function () {
    $hr = account(Role::HrAdmin);
    $target = User::factory()->create();

    // HR owns the dossier, System Admin owns accounts and roles (§9.2).
    expect($hr->can('viewAny', User::class))->toBeFalse()
        ->and($hr->can('update', $target))->toBeFalse()
        ->and($hr->can('assignRoles', $target))->toBeFalse();
});

it('stops anyone changing their own roles', function () {
    $admin = account(Role::SystemAdmin);

    // The one change that could silently widen your own access.
    expect($admin->can('assignRoles', $admin))->toBeFalse()
        ->and($admin->can('view', $admin))->toBeTrue();
});

it('keeps a company-pinned admin out of another company accounts', function () {
    $own = Company::factory()->create();
    $other = Company::factory()->create();

    $admin = account(Role::SystemAdmin, $own);
    $sameCompany = User::factory()->create(['company_id' => $own->id]);
    $otherCompany = User::factory()->create(['company_id' => $other->id]);

    expect($admin->can('update', $sameCompany))->toBeTrue()
        ->and($admin->can('update', $otherCompany))->toBeFalse()
        ->and($admin->can('assignRoles', $otherCompany))->toBeFalse();
});

it('stops a company-pinned admin touching a parent-wide account', function () {
    $admin = account(Role::SystemAdmin, Company::factory()->create());
    $parentWide = User::factory()->create(['company_id' => null]);

    // A parent-wide account outranks a company-scoped administrator's scope.
    expect($admin->can('update', $parentWide))->toBeFalse()
        ->and($admin->can('assignRoles', $parentWide))->toBeFalse();
});

it('lets anyone view their own account', function () {
    $user = account(Role::Employee);

    expect($user->can('view', $user))->toBeTrue()
        ->and($user->can('view', User::factory()->create()))->toBeFalse();
});

it('never allows an account to be deleted', function () {
    $admin = account(Role::SystemAdmin);

    // Offboarding revokes access; the record stays for the audit trail (OFF-CHK-010).
    expect($admin->can('delete', User::factory()->create()))->toBeFalse();
});
