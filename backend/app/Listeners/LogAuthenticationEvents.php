<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\Access\Entrance;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;

/**
 * What an attacker leaves behind, written where somebody can look (CORE-ACC-005).
 *
 * Sign-ins and two-factor changes go on the audit trail, one row each. Failed attempts, throttled requests and wrong
 * two-factor codes go to the application log instead, because anyone on the internet can produce them in
 * bulk and the audit table should not grow with them. The application log is the container's output, so a
 * log collector (or `docker compose logs app | grep 'Sign-in failed'`) is how password spraying shows up.
 * Neither ever holds a password, and a failed attempt keeps only the last four digits of the NIK.
 */
class LogAuthenticationEvents
{
    private const FAILURE_LOGGED = 'sign_in_failure_logged';

    public function __construct(private Entrance $entrance) {}

    public function handleLogin(Login $event): void
    {
        if ($event->user instanceof User) {
            $this->audit($event->user, 'Signed in');
        }
    }

    public function handleFailed(Failed $event): void
    {
        // Fortify checks the credentials in two steps of the same request, and each fires this event.
        if (request()->attributes->has(self::FAILURE_LOGGED)) {
            return;
        }

        request()->attributes->set(self::FAILURE_LOGGED, true);

        Log::warning('Sign-in failed', $this->context(['login' => $this->maskedLogin((string) ($event->credentials[$this->usernameKey()] ?? ''))]));
    }

    /**
     * Called by the rate limiters in FortifyServiceProvider when a request is turned away. Fortify's own `Lockout`
     * event never fires here, because the limiters are named ones and so run as middleware, ahead of Fortify.
     */
    public function logThrottled(string $message, Request $request): void
    {
        Log::warning($message, $this->context(['login' => $this->maskedLogin((string) $request->input($this->usernameKey()))]));
    }

    public function handleTwoFactorFailed(TwoFactorAuthenticationFailed $event): void
    {
        Log::warning('Two-factor code refused', $this->context(['user_id' => $event->user->getAuthIdentifier()]));
    }

    public function handleTwoFactorConfirmed(TwoFactorAuthenticationConfirmed $event): void
    {
        $this->audit($event->user, 'Two-factor authentication turned on');
    }

    public function handleTwoFactorDisabled(TwoFactorAuthenticationDisabled $event): void
    {
        $this->audit($event->user, 'Two-factor authentication turned off');
    }

    private function usernameKey(): string
    {
        return (string) config('fortify.username');
    }

    private function audit(User $user, string $description): void
    {
        activity('auth')
            ->causedBy($user)
            ->performedOn($user)
            ->withProperties($this->context())
            ->log($description);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function context(array $extra = []): array
    {
        $request = request();

        return [
            ...$extra,
            'ip' => $request->ip(),
            'entrance' => $this->entrance->isPublic() ? 'public' : 'private',
            'user_agent' => Str::limit((string) $request->userAgent(), 200, ''),
        ];
    }

    /**
     * "NIK|ID" becomes "...1234" (the NIK's last four digits); an email, which only administrator accounts use and
     * only on the private entrance, is kept as typed.
     */
    private function maskedLogin(string $login): string
    {
        if (! str_contains($login, '|')) {
            return Str::limit($login, 100, '');
        }

        return '...'.substr(trim(Str::before($login, '|')), -4);
    }
}
