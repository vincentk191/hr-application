<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RoleSeeder;

/**
 * Spike S0: the same tenancy guarantees must hold over the JSON API that a standalone SPA would
 * use, authenticated by Sanctum rather than the web guard.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function apiUser(Role $role, ?Company $company = null): User
{
    $user = User::factory()->create(['company_id' => $company?->id]);
    $user->assignRole($role->value);

    return $user;
}

/**
 * Sign in the way the SPA does: a session cookie on a request whose Referer is a stateful
 * domain, which is what makes Sanctum start the session for an API route.
 */
function signInAsSpa(User $user): void
{
    test()->actingAs($user)->withHeaders(['Referer' => 'http://localhost:5174']);
}

it('rejects unauthenticated API requests with 401 JSON', function () {
    $this->getJson('/api/v1/employees')->assertUnauthorized();
    $this->getJson('/api/v1/context')->assertUnauthorized();
});

it('returns the user, permissions and switcher state', function () {
    $company = Company::factory()->create();
    signInAsSpa(apiUser(Role::HrAdmin, $company));

    $this->getJson('/api/v1/context')
        ->assertOk()
        ->assertJsonPath('data.companySwitcher.locked', true)
        ->assertJsonPath('data.companySwitcher.selected', (string) $company->id)
        ->assertJsonPath('data.user.roles.0', Role::HrAdmin->value);
});

it('scopes a pinned user to their own company and hides foreign records', function () {
    $own = Company::factory()->create();
    $other = Company::factory()->create();
    $mine = Employee::factory()->forCompany($own)->create();
    $foreign = Employee::factory()->forCompany($other)->create();
    signInAsSpa(apiUser(Role::HrAdmin, $own));

    $this->getJson('/api/v1/employees')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id);

    $this->getJson("/api/v1/employees/{$mine->id}")->assertOk();
    $this->getJson("/api/v1/employees/{$foreign->id}")->assertNotFound();
});

it('lets a parent-wide user switch company over the API', function () {
    $a = Company::factory()->create();
    $b = Company::factory()->create();
    Employee::factory()->forCompany($a)->create();
    Employee::factory()->forCompany($b)->count(2)->create();
    signInAsSpa(apiUser(Role::SystemAdmin));

    $this->getJson('/api/v1/employees')->assertJsonCount(3, 'data');

    $this->putJson('/api/v1/active-company', ['company' => $b->id])->assertOk();
    $this->getJson('/api/v1/employees')->assertJsonCount(2, 'data');
});

it('refuses a pinned user the switcher and rejects invalid selections', function () {
    $own = Company::factory()->create();
    $other = Company::factory()->create();
    signInAsSpa(apiUser(Role::HrAdmin, $own));

    $this->putJson('/api/v1/active-company', ['company' => $other->id])->assertForbidden();

    signInAsSpa(apiUser(Role::SystemAdmin));
    $this->putJson('/api/v1/active-company', ['company' => 99999])->assertUnprocessable();
});

it('gives a parent-wide caller without a session the combined view instead of failing', function () {
    $a = Company::factory()->create();
    Employee::factory()->forCompany($a)->create();
    Employee::factory()->forCompany(Company::factory()->create())->create();

    $this->actingAs(apiUser(Role::SystemAdmin))->getJson('/api/v1/employees')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});
