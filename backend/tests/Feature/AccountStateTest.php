<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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

test('an employee can sign in with their employee ID', function () {
    $employee = Employee::factory()->withLogin()->create(['employee_number' => 'KRY-0042']);

    $this->post(route('login.store'), ['email' => 'kry-0042', 'password' => 'password']);

    $this->assertAuthenticatedAs($employee->user);
});

test('an employee ID shared by two companies is refused', function () {
    $a = Employee::factory()->forCompany(Company::factory()->create())->withLogin()->create(['employee_number' => '007']);
    Employee::factory()->forCompany(Company::factory()->create())->withLogin()->create(['employee_number' => '007']);

    $this->post(route('login.store'), ['email' => '007', 'password' => 'password']);

    $this->assertGuest();
});

test('a wrong password with an employee ID fails', function () {
    Employee::factory()->withLogin()->create(['employee_number' => 'KRY-1']);

    $this->post(route('login.store'), ['email' => 'KRY-1', 'password' => 'wrong']);

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
