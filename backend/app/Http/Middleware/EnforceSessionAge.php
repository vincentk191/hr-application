<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Caps how long one sign-in lasts, however busy the session has been. The idle timeout (`session.lifetime`) is
 * long so people are not asked for a code several times a day; this is the other half, so a session that is
 * never left idle (a tab left open on a shared computer) still ends (`session.absolute_lifetime`, minutes, 0 to
 * turn the cap off).
 *
 * The clock starts at the first request after signing in. Signing out flushes the session, so a new sign-in
 * always starts a new clock.
 */
class EnforceSessionAge
{
    public const SIGNED_IN_AT = 'signed_in_at';

    public function handle(Request $request, Closure $next): Response
    {
        $limit = (int) config('session.absolute_lifetime');

        if ($request->user() === null || $limit <= 0) {
            return $next($request);
        }

        $startedAt = $request->session()->get(self::SIGNED_IN_AT);

        if ($startedAt === null) {
            $request->session()->put(self::SIGNED_IN_AT, now()->getTimestamp());

            return $next($request);
        }

        if (now()->getTimestamp() - (int) $startedAt < $limit * 60) {
            return $next($request);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Sesi Anda berakhir. Silakan masuk kembali.');
    }
}
