<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SystemAdminSeeder;
use Illuminate\Support\Facades\Hash;

/**
 * The first System Admin of a new server: parent-wide, so it can create the companies and then each
 * company's first HR Admin in the admin panel.
 */
beforeEach(function () {
    config([
        'initial_admin.name' => 'Pemilik Sistem',
        'initial_admin.email' => 'Admin@Example.com',
        'initial_admin.password' => 'a-long-temporary-secret',
    ]);
});

it('creates a company-less system admin who must change the temporary password', function () {
    $this->seed(SystemAdminSeeder::class);

    $admin = User::where('email', 'admin@example.com')->firstOrFail();

    expect($admin->name)->toBe('Pemilik Sistem')
        ->and($admin->company_id)->toBeNull()
        ->and($admin->isParentWide())->toBeTrue()
        ->and($admin->is_active)->toBeTrue()
        ->and($admin->must_change_password)->toBeTrue()
        ->and($admin->hasVerifiedEmail())->toBeTrue()
        ->and($admin->getRoleNames()->all())->toBe([Role::SystemAdmin->value])
        ->and(Hash::check('a-long-temporary-secret', $admin->password))->toBeTrue()
        ->and(Company::query()->count())->toBe(0);
});

it('lets the admin it creates manage companies and users but not HR work', function () {
    $this->seed(SystemAdminSeeder::class);

    $admin = User::where('email', 'admin@example.com')->firstOrFail();

    expect($admin->can('manage-companies'))->toBeTrue()
        ->and($admin->can('manage-users'))->toBeTrue()
        ->and($admin->can('access-admin-panel'))->toBeTrue()
        ->and($admin->can('create-employees'))->toBeFalse();
});

it('creates nothing and says why when the password is missing or too short', function (?string $password) {
    config(['initial_admin.password' => $password]);

    $this->artisan('db:seed', ['--class' => SystemAdminSeeder::class])
        ->expectsOutputToContain('INITIAL_ADMIN_PASSWORD')
        ->assertSuccessful();

    expect(User::query()->count())->toBe(0);
})->with([
    'not set' => [null],
    'eleven characters' => ['elevenchars'],
]);

it('creates nothing when the email is missing or not an address', function (?string $email) {
    config(['initial_admin.email' => $email]);

    $this->artisan('db:seed', ['--class' => SystemAdminSeeder::class])
        ->expectsOutputToContain('INITIAL_ADMIN_EMAIL')
        ->assertSuccessful();

    expect(User::query()->count())->toBe(0);
})->with([
    'not set' => [null],
    'not an address' => ['not-an-address'],
]);

it('leaves an existing account with that email as it is when run again', function () {
    $this->seed(SystemAdminSeeder::class);

    $admin = User::where('email', 'admin@example.com')->firstOrFail();
    $admin->forceFill(['password' => 'a-password-chosen-later', 'must_change_password' => false])->save();

    config(['initial_admin.password' => 'a-different-temporary-secret']);
    $this->seed(SystemAdminSeeder::class);

    $admin->refresh();

    expect(User::query()->count())->toBe(1)
        ->and(Hash::check('a-password-chosen-later', $admin->password))->toBeTrue()
        ->and($admin->must_change_password)->toBeFalse();
});

it('seeds only the roles and the system admin, with no placeholder companies, in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->seed(DatabaseSeeder::class);

    expect(Company::query()->count())->toBe(0)
        ->and(User::query()->pluck('email')->all())->toBe(['admin@example.com'])
        ->and(User::first()->hasRole(Role::SystemAdmin->value))->toBeTrue();
});

it('still seeds the placeholder companies and a test user outside production', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Company::query()->count())->toBe(3)
        ->and(User::where('email', 'test@example.com')->exists())->toBeTrue()
        ->and(User::where('email', 'admin@example.com')->exists())->toBeFalse();
});
