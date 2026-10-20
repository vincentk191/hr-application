<?php

namespace App\Http\Middleware;

use App\Support\Access\Entrance;
use App\Support\Access\PublicRoutes;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * On the public entrance, serves only the routes classed as public (CORE-ACC-002). Anything else, including a
 * route nobody has classed, is refused whoever is asking.
 */
class EnforceEntrance
{
    public function __construct(private Entrance $entrance) {}

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();

        if ($this->entrance->isPublic() && ($route === null || ! PublicRoutes::servedPublicly($route))) {
            abort(403, 'Halaman ini hanya bisa dibuka lewat VPN.');
        }

        return $next($request);
    }
}
