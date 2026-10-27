<?php

namespace App\Http\Middleware;

use App\Support\Access\AccessTier;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Two-factor sign-in is mandatory for elevated accounts (CORE-ACC-005): anyone who can see or decide more than
 * their own data. Until one is set up, the only places open are the ones needed to set it up. Once it is on, an
 * elevated account cannot switch it off again; an administrator resets it for someone who lost their device.
 *
 * Plain employees are only encouraged to use it. `entrance.require_two_factor` turns the rule off for local
 * development and tests.
 */
class EnforceTwoFactor
{
    /**
     * Routes an elevated account without two-factor may still reach: enrolling, confirming its password for that,
     * and signing out.
     *
     * @var list<string>
     */
    private const ALLOWED_WHILE_ENROLLING = [
        'security.edit',
        'password.confirm',
        'password.confirm.store',
        'password.confirmation',
        'two-factor.*',
        'logout',
        'filament.admin.auth.logout',
    ];

    public function __construct(private AccessTier $tier) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! config('entrance.require_two_factor')) {
            return $next($request);
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            abort_if($request->routeIs('two-factor.disable') && $this->tier->isElevated($user), 403, 'Autentikasi dua langkah wajib untuk akun ini. Minta administrator mengatur ulangnya.');

            return $next($request);
        }

        if ($request->routeIs(...self::ALLOWED_WHILE_ENROLLING) || ! $this->tier->isElevated($user)) {
            return $next($request);
        }

        Inertia::flash('toast', [
            'type' => 'error',
            'message' => 'Akun Anda wajib memakai autentikasi dua langkah. Aktifkan untuk melanjutkan.',
        ]);

        return redirect()->route('security.edit');
    }
}
