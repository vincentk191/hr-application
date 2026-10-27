<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ignores "remember me" on the sign-in routes. A remember cookie signs a person back in after their session has
 * ended without a password and without the two-factor code, which would undo both the idle limit and mandatory
 * two-factor. The form no longer offers it; this keeps a hand-made request from asking for one.
 */
class DiscardRememberMe
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->request->remove('remember');

        return $next($request);
    }
}
