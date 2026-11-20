<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\User;
use App\Support\Access\SupervisorReach;
use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves which company the signed-in user is looking at, for the rest of the request.
 *
 * The decision is made here, from the user's own record, and never from what the browser
 * sent. A company-pinned user is resolved to their own company whatever the session happens
 * to hold, which is what makes the switcher's lock a server-side guarantee rather than a
 * hidden UI control (CORE-NAV-004). The one exception is a Supervisor whose team works in other
 * companies: they may pick among the companies their team reaches (SupervisorReach), and only
 * those, checked here on every request.
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

    public function __construct(private CompanyContext $context, private SupervisorReach $reach) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! $user->isParentWide()) {
            $this->resolvePinned($request, $user);

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
     * A pinned account is in its own company, unless it is a Supervisor whose team reaches others and has chosen one of
     * those (or all of them) in the session. A selection outside the reach is ignored, whatever the session holds.
     */
    private function resolvePinned(Request $request, User $user): void
    {
        $this->reach->flush();
        $reachable = $this->reach->companyIds($user);

        if (count($reachable) > 1) {
            $selected = $request->session()->get(self::SESSION_KEY);

            if ($selected === self::COMBINED) {
                $this->context->setCompanies($reachable, (int) $user->company_id);

                return;
            }

            if (is_numeric($selected) && in_array((int) $selected, $reachable, strict: true)) {
                $this->context->setCompany((int) $selected);

                return;
            }
        }

        $this->context->setCompany((int) $user->company_id);
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
