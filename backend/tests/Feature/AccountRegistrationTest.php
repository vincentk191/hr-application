<?php

use App\Enums\EmploymentStatus;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Activitylog\Models\Activity;

/**
 * An employee claims the account for the profile HR created (CORE-REG).
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    RateLimiter::clear('register');

    $this->company = Company::factory()->create();
    $this->profile = Employee::factory()->forCompany($this->company)->create([
        'employee_number' => 'K-100', 'national_id' => '3174000000000100', 'full_name' => 'Budi Santoso',
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function registration(array $overrides = []): array
{
    return [
        'national_id' => '3174000000000100',
        'employee_number' => 'K-100',
        'password' => 'kata-sandi-rahasia-1',
        'password_confirmation' => 'kata-sandi-rahasia-1',
        ...$overrides,
    ];
}

it('shows the registration page to guests only, with a link from the login page', function () {
    $this->get(route('register'))->assertOk();
    $this->get(route('login'))->assertOk()->assertInertia(fn ($page) => $page->where('canRegister', true));

    $this->actingAs(User::factory()->create())->get(route('register'))->assertRedirect();
});

it('creates an account that waits for HR to activate it, and tells HR', function () {
    $hr = User::factory()->create(['company_id' => $this->company->id]);
    $hr->assignRole(Role::HrAdmin->value);

    $this->post(route('register.store'), registration())->assertRedirect(route('login'));

    $user = $this->profile->refresh()->user;

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Budi Santoso')
        ->and($user->email)->toBeNull()
        ->and($user->is_active)->toBeFalse()
        ->and($user->awaiting_activation)->toBeTrue()
        ->and($user->company_id)->toBe($this->company->id)
        ->and($user->getRoleNames()->all())->toBe([Role::Employee->value])
        ->and($hr->notifications()->get()->pluck('data.title')->implode(' '))->toContain('menunggu aktivasi');

    // The right password is not enough until HR has activated it, and it says why.
    $this->post(route('login.store'), ['email' => '3174000000000100|K-100', 'password' => 'kata-sandi-rahasia-1'])
        ->assertSessionHasErrors(['email' => 'Akun Anda menunggu aktivasi HR.']);
    $this->assertGuest();

    // A wrong password learns nothing about the account.
    $this->post(route('login.store'), ['email' => '3174000000000100|K-100', 'password' => 'salah'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('lets HR activate the account, after which the employee signs in with NIK and employee ID', function () {
    $hr = User::factory()->create(['company_id' => $this->company->id]);
    $hr->assignRole(Role::HrAdmin->value);
    $this->post(route('register.store'), registration())->assertRedirect(route('login'));
    $this->app['auth']->forgetGuards();

    $this->actingAs($hr)->get(route('employees.show', $this->profile))
        ->assertInertia(fn ($page) => $page->where('accountPending', true)->where('canActivateAccount', true));

    $this->actingAs($hr)->post(route('employees.activation.activate', $this->profile))->assertRedirect();

    $user = $this->profile->refresh()->user->refresh();
    expect($user->is_active)->toBeTrue()->and($user->awaiting_activation)->toBeFalse()
        ->and($this->profile->accountLabel())->toBe('Aktif')
        ->and(Activity::query()->where('log_name', 'account')->pluck('description')->all())->toContain('HR mengaktifkan akun yang didaftarkan karyawan');

    $this->post('/logout');
    $this->post(route('login.store'), ['email' => '3174000000000100|k-100', 'password' => 'kata-sandi-rahasia-1'])->assertRedirect();
    $this->assertAuthenticatedAs($user);

    // Nothing left to activate.
    $this->actingAs($hr)->post(route('employees.activation.activate', $this->profile))->assertSessionHasErrors('account');
});

it('lets HR reject a claim, freeing the profile for the right person', function () {
    $hr = User::factory()->create(['company_id' => $this->company->id]);
    $hr->assignRole(Role::HrAdmin->value);
    $this->post(route('register.store'), registration())->assertRedirect(route('login'));
    $claimant = $this->profile->refresh()->user_id;

    $this->actingAs($hr)->delete(route('employees.activation.reject', $this->profile))->assertRedirect();

    expect($this->profile->refresh()->user_id)->toBeNull()->and(User::find($claimant))->toBeNull();

    $this->post('/logout');
    $this->post(route('register.store'), registration(['password' => 'kata-sandi-asli-2', 'password_confirmation' => 'kata-sandi-asli-2']))->assertRedirect(route('login'));
    expect($this->profile->refresh()->user_id)->not->toBeNull();
});

it('keeps activation to HR, and to people who can reach that company', function () {
    $this->post(route('register.store'), registration())->assertRedirect(route('login'));
    $this->app['auth']->forgetGuards();

    $employee = User::factory()->create(['company_id' => $this->company->id]);
    $employee->assignRole(Role::Employee->value);
    $otherHr = User::factory()->create(['company_id' => Company::factory()->create()->id]);
    $otherHr->assignRole(Role::HrAdmin->value);

    $this->actingAs($employee)->post(route('employees.activation.activate', $this->profile))->assertForbidden();
    // Another company's profile does not exist for them.
    $this->actingAs($otherHr)->post(route('employees.activation.activate', $this->profile))->assertNotFound();
    expect($this->profile->refresh()->user->awaiting_activation)->toBeTrue();
});

it('keeps an optional email, lower-cased, without asking to verify it', function () {
    $this->post(route('register.store'), registration(['email' => 'Budi@Example.COM']))->assertRedirect(route('login'));

    $user = $this->profile->refresh()->user;

    expect($user->email)->toBe('budi@example.com')->and($user->hasVerifiedEmail())->toBeTrue();
});

it('refuses an email another account already uses', function () {
    User::factory()->create(['email' => 'dipakai@example.com']);

    $this->post(route('register.store'), registration(['email' => 'dipakai@example.com']))->assertSessionHasErrors('email');

    expect($this->profile->refresh()->user_id)->toBeNull();
});

it('refuses every kind of mismatch with the same message and creates nothing', function () {
    Employee::factory()->forCompany($this->company)->create(['employee_number' => 'K-200', 'national_id' => '3174000000000200']);
    Employee::factory()->forCompany($this->company)->inactive()->create(['employee_number' => 'K-300', 'national_id' => '3174000000000300']);
    $registered = Employee::factory()->forCompany($this->company)->withLogin()->create(['employee_number' => 'K-400', 'national_id' => '3174000000000400']);
    $users = User::count();

    $attempts = [
        'a NIK with another employee\'s ID' => ['national_id' => '3174000000000100', 'employee_number' => 'K-200'],
        'a NIK that does not exist' => ['national_id' => '3174999999999999'],
        'an ID that does not exist' => ['employee_number' => 'NOPE'],
        'a former employee' => ['national_id' => '3174000000000300', 'employee_number' => 'K-300'],
        'a profile that is already registered' => ['national_id' => '3174000000000400', 'employee_number' => 'K-400'],
    ];

    foreach ($attempts as $override) {
        $response = $this->post(route('register.store'), registration($override));

        // The same message whatever part was wrong, so the form says nothing about which NIKs exist.
        $response->assertSessionHasErrors(['registration' => 'Data tidak cocok atau akun sudah terdaftar. Hubungi HR.']);
    }

    expect(User::count())->toBe($users)
        ->and($registered->refresh()->user_id)->not->toBeNull()
        ->and(Employee::where('employment_status', EmploymentStatus::Inactive)->whereNotNull('user_id')->count())->toBe(0);
});

it('matches the employee ID regardless of case', function () {
    $this->post(route('register.store'), registration(['employee_number' => ' k-100 ']))->assertRedirect(route('login'));

    expect($this->profile->refresh()->user_id)->not->toBeNull();
});

it('does not let a second attempt take over a profile that was just claimed', function () {
    $this->post(route('register.store'), registration())->assertRedirect(route('login'));
    $first = $this->profile->refresh()->user_id;

    $this->post(route('register.store'), registration(['password' => 'ganti-sandi-lain-2', 'password_confirmation' => 'ganti-sandi-lain-2']))
        ->assertSessionHasErrors('registration');

    expect($this->profile->refresh()->user_id)->toBe($first);
});

it('requires the password to be confirmed and the fields to be filled in', function () {
    $this->post(route('register.store'), registration(['password_confirmation' => 'beda']))->assertSessionHasErrors('password');
    $this->post(route('register.store'), registration(['national_id' => '']))->assertSessionHasErrors('national_id');
    $this->post(route('register.store'), registration(['employee_number' => '']))->assertSessionHasErrors('employee_number');

    expect($this->profile->refresh()->user_id)->toBeNull();
});

it('writes the registration to the audit trail with where it came from', function () {
    $this->post(route('register.store'), registration());

    $activity = Activity::query()->where('log_name', 'account')->firstOrFail();

    expect($activity->description)->toBe('Karyawan mendaftarkan akunnya sendiri')
        ->and($activity->subject_id)->toBe($this->profile->id)
        ->and($activity->causer_id)->toBe($this->profile->refresh()->user_id)
        ->and($activity->properties['ip'])->not->toBeNull();
});

it('throttles repeated attempts', function () {
    foreach (range(1, 5) as $ignored) {
        $this->post(route('register.store'), registration(['national_id' => '3174999999999999']))->assertSessionHasErrors('registration');
    }

    $this->post(route('register.store'), registration())->assertTooManyRequests();

    expect($this->profile->refresh()->user_id)->toBeNull();
});

it('labels a record nobody has claimed as "Belum terdaftar" in the directory', function () {
    $hr = User::factory()->create(['company_id' => $this->company->id]);
    $hr->assignRole(Role::HrAdmin->value);
    $deactivated = Employee::factory()->forCompany($this->company)->withLogin()->create();
    $deactivated->user->forceFill(['is_active' => false])->save();

    $labels = collect(
        $this->actingAs($hr)->get(route('employees.index'))->viewData('page')['props']['employees'],
    )->pluck('accountLabel', 'name');

    expect($labels[$this->profile->full_name])->toBe('Belum terdaftar')
        ->and($labels[$deactivated->full_name])->toBe('Dinonaktifkan');
});

it('requires a 16-digit NIK that no one else has when HR saves a profile', function () {
    $hr = User::factory()->create(['company_id' => $this->company->id]);
    $hr->assignRole(Role::HrAdmin->value);

    $branch = Branch::factory()->forCompany($this->company)->create();

    $payload = fn (array $o = []): array => [
        'employee_number' => 'K-900', 'full_name' => 'Baru', 'employment_status' => 'active', 'national_id' => '3174000000000900', 'branch_id' => $branch->id, ...$o,
    ];

    $this->actingAs($hr)->post(route('employees.store'), $payload(['national_id' => '']))->assertSessionHasErrors(['national_id' => 'Isi NIK.']);
    $this->actingAs($hr)->post(route('employees.store'), $payload(['national_id' => '123']))->assertSessionHasErrors(['national_id' => 'NIK harus 16 digit angka.']);
    $this->actingAs($hr)->post(route('employees.store'), $payload(['national_id' => '3174000000000100']))->assertSessionHasErrors(['national_id' => 'NIK ini sudah terdaftar pada karyawan lain.']);

    // Unique across the group, not only the company.
    $other = Company::factory()->create();
    Employee::factory()->forCompany($other)->create(['national_id' => '3174000000000555']);
    $this->actingAs($hr)->post(route('employees.store'), $payload(['national_id' => '3174000000000555']))->assertSessionHasErrors('national_id');

    $this->actingAs($hr)->post(route('employees.store'), $payload())->assertRedirect();

    // Editing a record keeps its own NIK without tripping the uniqueness rule.
    $mine = Employee::firstWhere('employee_number', 'K-900');
    $this->actingAs($hr)->put(route('employees.update', $mine), $payload(['full_name' => 'Diubah']))->assertRedirect();
});
