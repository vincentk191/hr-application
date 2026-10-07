<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\CompanyContext;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                // Drives the sidebar's link to the admin panel; the panel enforces access itself.
                'canAccessAdminPanel' => $request->user()?->canAccessPanel(Filament::getDefaultPanel()) ?? false,
            ],
            'companySwitcher' => fn (): ?array => $this->companySwitcher($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * State for the header company switcher (CORE-NAV-001), or null when nobody is signed in.
     *
     * A company-pinned user is offered only their own company and the control is marked
     * locked. That is presentation only — the server-side guarantee is in SetActiveCompany
     * and CompanyScope, so hiding the options is never what keeps the data apart.
     *
     * @return array{
     *     companies: array<int, array{id: int, code: string, name: string}>,
     *     selected: string,
     *     canCombine: bool,
     *     locked: bool
     * }|null
     */
    private function companySwitcher(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        $context = app(CompanyContext::class);

        $companies = Company::active()
            ->when(! $user->isParentWide(), fn ($query) => $query->whereKey($user->company_id))
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return [
            'companies' => $companies->map(fn (Company $company): array => [
                'id' => $company->id,
                'code' => $company->code,
                'name' => $company->name,
            ])->all(),
            'selected' => $context->isCombined()
                ? SetActiveCompany::COMBINED
                : (string) $context->companyId(),
            'canCombine' => $user->isParentWide(),
            'locked' => ! $user->isParentWide(),
        ];
    }
}
