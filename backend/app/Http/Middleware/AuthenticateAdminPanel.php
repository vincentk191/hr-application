<?php

namespace App\Http\Middleware;

use Filament\Http\Middleware\Authenticate;

/**
 * Filament's own authentication, except that a guest is sent to the application's login.
 *
 * The panel has no login page of its own: people sign in once, through Fortify, with whatever
 * that flow requires (two-factor included), and the session is shared with the rest of the app.
 * Laravel remembers the page they were heading for, so they land back in the panel afterwards.
 */
class AuthenticateAdminPanel extends Authenticate
{
    protected function redirectTo($request): ?string
    {
        return route('login');
    }
}
