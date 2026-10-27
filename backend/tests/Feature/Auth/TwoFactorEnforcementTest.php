<?php

use App\Enums\Role;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/**
 * Two-factor sign-in is mandatory for elevated accounts (CORE-ACC-005). The suite runs with the rule off
 * (REQUIRE_TWO_FACTOR=false in phpunit.xml); these tests switch it on.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['entrance.require_two_factor' => true]);
    $this->company = Company::factory()->create();
});

function twoFactorUser(Role $role, bool $withTwoFactor = false): User
{
    // The system admin belongs to no company, which the admin panel requires.
    $user = User::factory()->when($withTwoFactor, fn ($factory) => $factory->withTwoFactor())
        ->create(['company_id' => $role === Role::SystemAdmin ? null : test()->company->id]);
    $user->assignRole($role->value);

    return $user;
}

it('sends an elevated account without two-factor to set it up', function (string $entrance) {
    $this->withHeaders(['X-Entrance' => $entrance])
        ->actingAs(twoFactorUser(Role::HrAdmin))
        ->get(route('dashboard'))
        ->assertRedirect(route('security.edit'));
})->with(['private', 'public']);

it('lets a plain employee carry on without two-factor', function () {
    $this->actingAs(twoFactorUser(Role::Employee))
        ->get(route('dashboard'))
        ->assertOk();
});

it('lets an elevated account that has two-factor through', function () {
    $this->actingAs(twoFactorUser(Role::HrAdmin, withTwoFactor: true))
        ->get(route('dashboard'))
        ->assertOk();
});

it('leaves the pages needed for setting up two-factor open to an elevated account without it', function () {
    $this->actingAs(twoFactorUser(Role::HrAdmin))
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('twoFactorEnabled', false)->where('twoFactorRequired', true));
});

it('keeps an elevated account without two-factor out of the admin panel', function () {
    $this->actingAs(twoFactorUser(Role::SystemAdmin))
        ->get('/admin')
        ->assertRedirect(route('security.edit'));
});

it('does nothing when the requirement is switched off', function () {
    config(['entrance.require_two_factor' => false]);

    $this->actingAs(twoFactorUser(Role::HrAdmin))
        ->get(route('dashboard'))
        ->assertOk();
});

it('refuses to let an elevated account turn two-factor off', function () {
    $user = twoFactorUser(Role::HrAdmin, withTwoFactor: true);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('two-factor.disable'))
        ->assertForbidden();

    expect($user->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();
});

it('lets a plain employee turn two-factor off', function () {
    $user = twoFactorUser(Role::Employee, withTwoFactor: true);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('two-factor.disable'))
        ->assertRedirect();

    expect($user->fresh()->hasEnabledTwoFactorAuthentication())->toBeFalse();
});

it('tells a plain employee that two-factor is optional for them', function () {
    $this->actingAs(twoFactorUser(Role::Employee))
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('twoFactorRequired', false));
});

it('lets an administrator reset two-factor for someone who lost their device', function () {
    $admin = twoFactorUser(Role::SystemAdmin, withTwoFactor: true);
    $lost = twoFactorUser(Role::HrAdmin, withTwoFactor: true);
    DB::table('sessions')->insert(['id' => 'lost-phone', 'user_id' => $lost->id, 'payload' => '', 'last_activity' => time()]);

    $this->actingAs($admin);

    Livewire::test(ListUsers::class)->callTableAction('resetTwoFactor', $lost);

    expect($lost->fresh())
        ->two_factor_secret->toBeNull()
        ->two_factor_recovery_codes->toBeNull()
        ->two_factor_confirmed_at->toBeNull();
    expect(DB::table('sessions')->where('user_id', $lost->id)->exists())->toBeFalse();
    expect(Activity::query()->where('log_name', 'auth')->where('subject_id', $lost->id)->where('causer_id', $admin->id)->exists())->toBeTrue();
});

it('does not offer the two-factor reset for an account that has none', function () {
    $this->actingAs(twoFactorUser(Role::SystemAdmin, withTwoFactor: true));

    Livewire::test(ListUsers::class)->assertTableActionHidden('resetTwoFactor', twoFactorUser(Role::Employee));
});
