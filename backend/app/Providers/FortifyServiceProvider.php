<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\LoginResponse;
use App\Http\Responses\TwoFactorLoginResponse;
use App\Models\Employee;
use App\Models\User;
use App\Support\Access\Entrance;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Signing in never lands anyone in the admin panel on its own.
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(TwoFactorLoginResponseContract::class, TwoFactorLoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureAuthentication();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
    }

    /**
     * Who may sign in, and with what (CORE-AUT-002, CORE-ACC-007).
     *
     * The form sends NIK and employee ID together as "NIK|ID" in the login field: the pair identifies one
     * person across the group (the NIK is unique, the ID is unique within a company), so there is no shared-ID
     * ambiguity and an ID alone is no longer enough. An email works only on the private entrance, for the
     * seeded admin accounts. A deactivated account fails with the same message as a wrong password. An account
     * waiting for HR to activate it says so, but only once the password has proved the caller owns it.
     */
    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = $this->resolveLogin(trim((string) $request->input(Fortify::username())));

            if ($user === null || ! Hash::check((string) $request->input('password'), $user->password)) {
                return null;
            }

            if ($user->awaiting_activation) {
                throw ValidationException::withMessages([Fortify::username() => 'Akun Anda menunggu aktivasi HR.']);
            }

            return $user->is_active ? $user : null;
        });
    }

    private function resolveLogin(string $login): ?User
    {
        if ($login === '') {
            return null;
        }

        if (str_contains($login, '|')) {
            [$nik, $number] = array_map('trim', array_pad(explode('|', $login, 2), 2, ''));

            if ($nik === '' || $number === '') {
                return null;
            }

            $employee = Employee::acrossCompanies()
                ->where('national_id', $nik)
                ->whereRaw('lower(employee_number) = ?', [Str::lower($number)])
                ->whereNotNull('user_id')
                ->first();

            return $employee?->user;
        }

        if (str_contains($login, '@') && ! app(Entrance::class)->isPublic()) {
            return User::query()->where('email', Str::lower($login))->first();
        }

        return null;
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            // A reset is by HR or an administrator, never by email (CORE-ACC-005).
            'canResetPassword' => false,
            'canRegister' => true,
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        // Registration is an open door onto "which NIKs exist", so it is throttled per address.
        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // Per account and address, per address alone, and per account from anywhere, so neither a guesser from
        // one address nor a spread-out one can wear a password down (CORE-ACC-005). The per-account limit means
        // someone can slow a victim's sign-in by guessing; HR can reset a locked person.
        RateLimiter::for('login', function (Request $request) {
            $account = Str::transliterate(Str::lower((string) $request->input(Fortify::username())));

            return [
                Limit::perMinute(5)->by($account.'|'.$request->ip()),
                Limit::perMinute(20)->by('ip|'.$request->ip()),
                Limit::perMinute(10)->by('account|'.$account),
            ];
        });

    }
}
