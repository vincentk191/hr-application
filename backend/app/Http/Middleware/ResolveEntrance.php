<?php

namespace App\Http\Middleware;

use App\Support\Access\Entrance;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * First in line: works out which entrance this request used and gives each entrance its own session cookie,
 * so a session from the private entrance is never valid on the public one (CORE-ACC-001).
 */
class ResolveEntrance
{
    public function __construct(private Entrance $entrance) {}

    public function handle(Request $request, Closure $next): Response
    {
        $public = Entrance::publicFor($request);

        $this->entrance->markPublic($public);
        config(['session.cookie' => config('entrance.session_cookies.'.($public ? 'public' : 'private'))]);

        return $next($request);
    }
}
