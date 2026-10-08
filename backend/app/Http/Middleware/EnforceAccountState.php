<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the two account states that must hold on every request, not just at login.
 *
 * A deactivated account is signed out wherever it is (CORE-USR-002); revoking its sessions makes
 * this rare, but a request that slips in before that, or a remember-me cookie, must still fail. An
 * account holding a temporary password can do nothing but change it (CORE-AUT-003): every other page
 * sends it to the security settings, so a password an administrator chose cannot stay in use.
 */
class EnforceAccountState
{
    /**
     * Routes a user with a temporary password may still reach: the password change itself, the
     * confirmation step in front of it, two-factor setup, and signing out.
     *
     * @var list<string>
     */
    private const ALLOWED_WHILE_CHANGING = [
        'security.edit',
        'user-password.update',
        'password.confirm',
        'password.confirm.store',
        'password.confirmation',
        'two-factor.*',
        'logout',
        'filament.admin.auth.logout',
        'verification.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Akun ini dinonaktifkan. Hubungi administrator.']);
        }

        if ($user->must_change_password && ! $request->routeIs(...self::ALLOWED_WHILE_CHANGING)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Anda memakai kata sandi sementara. Ganti kata sandi untuk melanjutkan.',
            ]);

            return redirect()->route('security.edit');
        }

        return $next($request);
    }
}
