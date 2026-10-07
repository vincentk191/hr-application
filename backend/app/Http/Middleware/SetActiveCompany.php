<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves which company the signed-in user is looking at, for the rest of the request.
 *
 * The decision is made here, from the user's own record, and never from what the browser
 * sent. A company-pinned user is always resolved to their own company whatever the session
 * happens to hold, which is what makes the switcher's lock a server-side guarantee rather
 * than a hidden UI control (CORE-NAV-004).
 */
class SetActiveCompany
{
    /**
     * The session key holding a parent-wide user's current selection.
     */
    public const SESSION_KEY = 'active_company_id';

    /**
     * The value a parent-wide user's selection takes when viewing every company at once.
     */
    public const COMBINED = 'all';

    public function __construct(private CompanyContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! $user->isParentWide()) {
            // Pinned accounts ignore the session entirely.
            $this->context->setCompany($user->company_id);

            return $next($request);
        }

        // A bearer-token request has no session to remember a selection in, so a parent-wide
        // token sees every company; narrowing is a stateful (cookie) feature.
        if (! $request->hasSession()) {
            $this->context->setCombined();

            return $next($request);
        }

        $selected = $request->session()->get(self::SESSION_KEY);

        if ($selected === self::COMBINED) {
            $this->context->setCombined();

            return $next($request);
        }

        $company = $this->resolveSelectedCompany($selected);

        if ($company === null) {
            // No usable selection yet: start combined rather than guessing a company.
            $request->session()->put(self::SESSION_KEY, self::COMBINED);
            $this->context->setCombined();

            return $next($request);
        }

        $this->context->setCompany($company);

        return $next($request);
    }

    /**
     * A stored selection is only honoured while it still names an active company, so a
     * company that was deactivated or removed cannot keep scoping someone's session.
     */
    private function resolveSelectedCompany(mixed $selected): ?Company
    {
        if (! is_numeric($selected)) {
            return null;
        }

        return Company::active()->find((int) $selected);
    }
}
