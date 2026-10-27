<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;

/**
 * What a sign-in leaves behind (CORE-ACC-005): the audit trail for successes, the application log for failures.
 */
it('records a successful sign-in on the audit trail', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    $row = Activity::query()->where('log_name', 'auth')->where('description', 'Signed in')->firstOrFail();

    expect($row->causer_id)->toBe($user->id)
        ->and($row->properties['entrance'])->toBe('private');
});

it('logs a failed sign-in with only the end of the NIK and never the password', function () {
    Log::spy();
    $employee = Employee::factory()->forCompany(Company::factory()->create())->create(['national_id' => '3201010101010001']);

    $this->post(route('login.store'), ['email' => $employee->national_id.'|'.$employee->employee_number, 'password' => 'a-wrong-password']);

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'Sign-in failed'
        && $context['login'] === '...0001'
        && ! str_contains(json_encode($context), 'a-wrong-password')
        && ! str_contains(json_encode($context), '3201010101010001'))->once();
});

it('turns a sixth attempt in a minute away with a 429 and logs it', function () {
    Log::spy();
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-'.$attempt])->assertSessionHasErrors('email');
    }

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-6'])->assertTooManyRequests();

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'Sign-in throttled'
        && $context['login'] === $user->email)->once();
});

it('checks a password hash even when no account matches the NIK and ID', function () {
    Hash::partialMock()->shouldReceive('check')->once()->andReturnFalse();

    $this->post(route('login.store'), ['email' => '3201010101010009|NOBODY', 'password' => 'whatever-it-is'])
        ->assertSessionHasErrors('email');
});

it('logs a refused two-factor code', function () {
    Log::spy();
    // A real base32 secret: the factory's placeholder is not one, and the code check would fail before it could be refused.
    $user = User::factory()->withTwoFactor()->create(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP')]);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $this->post(route('two-factor.login.store'), ['code' => '000000']);

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'Two-factor code refused'
        && $context['user_id'] === $user->id)->once();
});
