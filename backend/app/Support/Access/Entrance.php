<?php

namespace App\Support\Access;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Which entrance the current request came through (CORE-ACC-001): the private one over the VPN, or the
 * public one for employee self-service. One instance per request, set by `ResolveEntrance`. Outside a
 * request (queue, console, tests) it is private, because nothing there came from the internet.
 */
class Entrance
{
    private bool $public = false;

    private int $unlimited = 0;

    public function isPublic(): bool
    {
        return $this->public && $this->unlimited === 0;
    }

    public function markPublic(bool $public): void
    {
        $this->public = $public;
    }

    /**
     * Runs a callback as if on the private entrance. For asking what an account *could* do, such as whether
     * it is elevated, while the request itself is public.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withoutLimits(callable $callback): mixed
    {
        $this->unlimited++;

        try {
            return $callback();
        } finally {
            $this->unlimited--;
        }
    }

    /**
     * Whether the request came through the public entrance: the header from a trusted proxy, else the
     * configured default. Anything that is not exactly "private" counts as public.
     */
    public static function publicFor(Request $request): bool
    {
        $fromServer = $request->server->get((string) config('entrance.server_variable'));

        if (is_string($fromServer) && $fromServer !== '') {
            return strtolower(trim($fromServer)) !== 'private';
        }

        $value = $request->headers->get((string) config('entrance.header'));

        if ($value !== null && IpUtils::checkIp((string) $request->server->get('REMOTE_ADDR'), (array) config('entrance.trusted_proxies'))) {
            return strtolower(trim($value)) !== 'private';
        }

        return config('entrance.default') !== 'private';
    }
}
