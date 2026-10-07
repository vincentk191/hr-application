<?php

namespace App\Concerns;

use Illuminate\Http\Request;

/**
 * Stops signing in from landing anyone in the admin panel on its own.
 *
 * Laravel remembers the page a guest was heading for and sends them back to it after login. For
 * `/admin` that meant an ordinary user, who had once followed a link or stale bookmark there,
 * was thrown a 403 straight after signing in. The panel is something you choose to open — from
 * the sidebar link — so a remembered panel address is forgotten and they go to the dashboard.
 */
trait IgnoresAdminPanelRedirect
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request)
    {
        $intended = $request->session()->get('url.intended');

        if (is_string($intended) && $this->pointsAtAdminPanel($intended)) {
            $request->session()->forget('url.intended');
        }

        return parent::toResponse($request);
    }

    private function pointsAtAdminPanel(string $url): bool
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        return $path === 'admin' || str_starts_with($path, 'admin/');
    }
}
