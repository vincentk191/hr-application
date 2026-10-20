<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

test('a deactivated account cannot sign in', function () {
    $user = User::factory()->create(['is_active' => false]);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    $this->assertGuest();
});

test('deactivating a signed-in account signs it out on the next request', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    $user->forceFill(['is_active' => false])->save();

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('deactivation revokes sessions and tokens', function () {
    $user = User::factory()->create();
    DB::table('sessions')->insert([
        'id' => 'abc', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time(),
    ]);
    $user->createToken('t');

    $user->forceFill(['is_active' => false])->save();

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and($user->tokens()->count())->toBe(0);
});

test('a temporary password forces the security page until changed', function () {
    $user = User::factory()->create(['must_change_password' => true]);

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('security.edit'));
    $this->withSession(['auth.password_confirmed_at' => time()])->get(route('security.edit'))->assertOk();

    $this->put(route('user-password.update'), [
        'current_password' => 'password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    expect($user->fresh()->must_change_password)->toBeFalse();
    $this->get(route('dashboard'))->assertOk();
});

test('signing in records the last login', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    expect($user->fresh()->last_login_at)->not->toBeNull();
});

test('an employee signs in with NIK and employee ID together', function () {
    $employee = Employee::factory()->withLogin()->create(['employee_number' => 'KRY-0042', 'national_id' => '3174000000004200']);

    $this->post(route('login.store'), ['email' => '3174000000004200|kry-0042', 'password' => 'password']);

    $this->assertAuthenticatedAs($employee->user);
});

test('an employee ID alone, or a NIK with the wrong ID, no longer signs anyone in', function () {
    Employee::factory()->withLogin()->create(['employee_number' => 'KRY-1', 'national_id' => '3174000000000001']);

    foreach (['KRY-1', '3174000000000001', '3174000000000001|KRY-2', '3174000000000002|KRY-1', '|KRY-1', '3174000000000001|'] as $login) {
        $this->post(route('login.store'), ['email' => $login, 'password' => 'password']);
        $this->assertGuest();
    }
});

test('an employee ID shared by two companies still signs in, because the NIK tells them apart', function () {
    $a = Employee::factory()->forCompany(Company::factory()->create())->withLogin()->create(['employee_number' => '007', 'national_id' => '3174000000000007']);
    Employee::factory()->forCompany(Company::factory()->create())->withLogin()->create(['employee_number' => '007', 'national_id' => '3174000000000070']);

    $this->post(route('login.store'), ['email' => '3174000000000007|007', 'password' => 'password']);

    $this->assertAuthenticatedAs($a->user);
});

test('a wrong password with NIK and employee ID fails', function () {
    Employee::factory()->withLogin()->create(['employee_number' => 'KRY-1', 'national_id' => '3174000000000001']);

    $this->post(route('login.store'), ['email' => '3174000000000001|KRY-1', 'password' => 'wrong']);

    $this->assertGuest();
});

test('an email works on the private entrance only', function () {
    $user = User::factory()->create();

    $this->withHeaders(['X-Entrance' => 'public'])->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $this->assertGuest();

    $this->withHeaders(['X-Entrance' => 'private'])->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $this->assertAuthenticatedAs($user);
});

test('there is no password reset by email: a reset is by HR or an administrator', function () {
    expect(Route::has('password.email'))->toBeFalse()
        ->and(Route::has('password.reset'))->toBeFalse();

    $this->get(route('login'))->assertInertia(fn ($page) => $page->where('canResetPassword', false));
});

test('a guesser is slowed down however many addresses they use against one account', function () {
    $employee = Employee::factory()->withLogin()->create(['employee_number' => 'KRY-5', 'national_id' => '3174000000000005']);

    foreach (range(1, 10) as $i) {
        $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])->post(route('login.store'), ['email' => '3174000000000005|KRY-5', 'password' => 'wrong']);
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.9.9'])->post(route('login.store'), ['email' => '3174000000000005|KRY-5', 'password' => 'password'])->assertStatus(429);
    $this->assertGuest();
});

test('repeated failed logins are throttled', function () {
    $user = User::factory()->create();

    foreach (range(1, 6) as $ignored) {
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong']);
    }

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertStatus(429);
    $this->assertGuest();
});

test('revokeAccess signs the user out everywhere', function () {
    $user = User::factory()->create();
    DB::table('sessions')->insert([
        'id' => 'xyz', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time(),
    ]);
    $token = $user->remember_token;

    $user->revokeAccess();

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and($user->fresh()->remember_token)->not->toBe($token);
});
