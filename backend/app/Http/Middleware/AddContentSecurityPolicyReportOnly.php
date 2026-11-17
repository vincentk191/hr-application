<?php

namespace App\Http\Middleware;

use App\Support\Security\ContentSecurityPolicy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends the report-only script and style policy on the app's HTML pages, with a nonce for this request that the Vite
 * and font tags and the page's own inline script and style carry. Off unless `security.csp.report_only` is set.
 */
class AddContentSecurityPolicyReportOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('security.csp.report_only') || $request->is('admin', 'admin/*', 'livewire', 'livewire/*', 'filament/*')) {
            return $next($request);
        }

        $nonce = Vite::useCspNonce();

        $response = $next($request);

        if (str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')) {
            $response->headers->set('Content-Security-Policy-Report-Only', ContentSecurityPolicy::reportOnly($nonce));
        }

        return $response;
    }
}
