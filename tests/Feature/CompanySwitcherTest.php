<?php

use App\Http\Middleware\SetActiveCompany;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The switcher itself (CORE-NAV-001/003) and, more importantly, its lock: a company-pinned
 * user's scope is decided on the server from their own record, never from what the browser or
 * the session claims (CORE-NAV-004).
 */
function parentWideUser(): User
{
    return User::factory()->create(['company_id' => null]);
}

function pinnedUser(Company $company): User
{
    return User::factory()->create(['company_id' => $company->id]);
}

it('offers a parent-wide user every active company and the combined view', function () {
    $acme = Company::factory()->create(['name' => 'Acme']);
    $other = Company::factory()->create(['name' => 'Beta']);
    Company::factory()->inactive()->create();

    $this->actingAs(parentWideUser())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('companySwitcher.canCombine', true)
            ->where('companySwitcher.locked', false)
            ->where('companySwitcher.selected', SetActiveCompany::COMBINED)
            ->where('companySwitcher.companies', fn (Collection $companies) => $companies
                ->pluck('id')
                ->all() === [$acme->id, $other->id])
        );
});

it('lets a parent-wide user select a company', function () {
    $acme = Company::factory()->create();

    $this->actingAs(parentWideUser())
        ->from(route('dashboard'))
        ->put(route('active-company.update'), ['company' => $acme->id])
        ->assertRedirect(route('dashboard'));

    expect(session(SetActiveCompany::SESSION_KEY))->toBe((string) $acme->id);
});

it('lets a parent-wide user return to the combined view', function () {
    $this->actingAs(parentWideUser())
        ->from(route('dashboard'))
        ->put(route('active-company.update'), ['company' => SetActiveCompany::COMBINED])
        ->assertRedirect(route('dashboard'));

    expect(session(SetActiveCompany::SESSION_KEY))->toBe(SetActiveCompany::COMBINED);
});

it('locks the switcher for a company-pinned user', function () {
    $acme = Company::factory()->create();
    Company::factory()->create();

    $this->actingAs(pinnedUser($acme))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('companySwitcher.locked', true)
            ->where('companySwitcher.canCombine', false)
            ->where('companySwitcher.selected', (string) $acme->id)
            // Only their own company is offered, even though two exist.
            ->where('companySwitcher.companies', fn (Collection $companies) => $companies->count() === 1
                && $companies->first()['id'] === $acme->id)
        );
});

it('forbids a company-pinned user from switching company', function () {
    $acme = Company::factory()->create();
    $other = Company::factory()->create();

    $this->actingAs(pinnedUser($acme))
        ->put(route('active-company.update'), ['company' => $other->id])
        ->assertForbidden();
});

it('ignores a session selection that a pinned user should not have', function () {
    $acme = Company::factory()->create();
    $other = Company::factory()->create();

    // Even with another company planted in the session, the pinned user stays on their own.
    $this->actingAs(pinnedUser($acme))
        ->withSession([SetActiveCompany::SESSION_KEY => (string) $other->id])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('companySwitcher.selected', (string) $acme->id)
        );
});

it('falls back to the combined view when the selected company is no longer active', function () {
    $retired = Company::factory()->inactive()->create();

    $this->actingAs(parentWideUser())
        ->withSession([SetActiveCompany::SESSION_KEY => (string) $retired->id])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('companySwitcher.selected', SetActiveCompany::COMBINED)
        );
});

it('rejects a company that does not exist', function () {
    $this->actingAs(parentWideUser())
        ->from(route('dashboard'))
        ->put(route('active-company.update'), ['company' => 99999])
        ->assertSessionHasErrors('company');
});

it('rejects an inactive company', function () {
    $retired = Company::factory()->inactive()->create();

    $this->actingAs(parentWideUser())
        ->from(route('dashboard'))
        ->put(route('active-company.update'), ['company' => $retired->id])
        ->assertSessionHasErrors('company');
});

it('requires a selection', function () {
    $this->actingAs(parentWideUser())
        ->from(route('dashboard'))
        ->put(route('active-company.update'), [])
        ->assertSessionHasErrors('company');
});

it('does not share switcher state with a guest', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('companySwitcher', null));
});
