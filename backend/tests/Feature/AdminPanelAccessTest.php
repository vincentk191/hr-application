<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RoleSeeder;
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

it('makes the panel follow the app\'s light or dark choice instead of the operating system\'s', function () {
    $html = (string) $this->actingAs(panelUser(Role::SystemAdmin))->get('/admin')->assertOk()->getContent();

    $copiesTheApp = strpos($html, "localStorage.getItem('appearance')");
    $panelReadsItsOwn = strpos($html, "localStorage.getItem('theme')");

    // The copy has to come first, or the panel has already decided by the time it runs.
    expect($copiesTheApp)->not->toBeFalse()
        ->and($panelReadsItsOwn)->not->toBeFalse()
        ->and($copiesTheApp)->toBeLessThan($panelReadsItsOwn)
        ->and($html)->toContain("localStorage.setItem('theme', appearance)");
});

it('keeps a company-pinned admin out of the panel', function () {
    $this->actingAs(panelUser(Role::HrAdmin, Company::factory()->create()))
        ->get('/admin')
        ->assertForbidden();
});

it('keeps roles without the permission out of the panel', function (Role $role) {
    $this->actingAs(panelUser($role))->get('/admin')->assertForbidden();
})->with([Role::Management, Role::Supervisor, Role::Employee]);

it('sends guests to the application login rather than a panel login of its own', function () {
    $this->get('/admin')->assertRedirect(route('login'));
    $this->get('/admin/login')->assertNotFound();
});

it('does not land anyone in the admin panel after signing in, even if that is where they were heading', function () {
    $user = panelUser(Role::SystemAdmin);

    $this->get('/admin/companies')->assertRedirect(route('login'));

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard'));
});

it('still returns a guest to an ordinary page they were heading for', function () {
    $user = panelUser(Role::HrAdmin);

    $this->get('/employees')->assertRedirect(route('login'));

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/employees');
});

it('leads back to the dashboard from inside the panel', function () {
    $this->actingAs(panelUser(Role::SystemAdmin))->get('/admin')
        ->assertOk()
        ->assertSee('Kembali ke dashboard')
        ->assertSee(route('dashboard'), false);
});

it('follows a permission granted to a role in the database', function () {
    $user = panelUser(Role::Management);

    $this->actingAs($user)->get('/admin')->assertForbidden();

    Spatie\Permission\Models\Role::findByName(Role::Management->value)
        ->givePermissionTo(Permission::AccessAdminPanel->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($user->fresh())->get('/admin')->assertOk();
});

it('signs out of the whole application from the panel and lands on the login', function () {
    $this->actingAs(panelUser(Role::SystemAdmin))
        ->post('/admin/logout')
        ->assertRedirect('/admin');

    $this->assertGuest();
    $this->get('/admin')->assertRedirect(route('login'));
});
