<?php

namespace App\Providers\Filament;

use App\Filament\Pages\ManageWorkingHours;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\AttendanceAgents\AttendanceAgentResource;
use App\Filament\Resources\AttendanceIngestRequests\AttendanceIngestRequestResource;
use App\Filament\Resources\Branches\BranchResource;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\Departments\DepartmentResource;
use App\Filament\Resources\OffboardingItems\OffboardingItemResource;
use App\Filament\Resources\Positions\PositionResource;
use App\Filament\Resources\RequestTypes\RequestTypeResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\UserResource;
use App\Http\Middleware\AuthenticateAdminPanel;
use App\Http\Middleware\EnforceAccountState;
use App\Http\Middleware\EnforceEntrance;
use App\Http\Middleware\EnforceSessionAge;
use App\Http\Middleware\EnforceTwoFactor;
use App\Http\Middleware\SetActiveCompany;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    /**
     * Admin pages that are not resources. Like the resources, only registered where the panel is
     * served.
     *
     * @return list<class-string>
     */
    private function extraPages(): array
    {
        return $this->servesPanel() ? [ManageWorkingHours::class] : [];
    }

    private function servesPanel(): bool
    {
        return $this->app->runningInConsole()
            || $this->app->runningUnitTests()
            || request()->is('admin', 'admin/*', 'livewire*');
    }

    /**
     * Registering the resources costs about 0.7s a request here, and every request pays it
     * because panel providers boot with the application — including pages that have nothing to
     * do with the panel. So they are only registered where the panel is actually served: its own
     * pages, Livewire's requests (which carry every table search, sort and form action), the
     * console (route:list, Wayfinder) and the test suite.
     *
     * A new resource is added here, in one place, like the workflow registry.
     *
     * @return list<class-string>
     */
    private function resources(): array
    {
        if (! $this->servesPanel()) {
            return [];
        }

        return [
            ActivityLogResource::class,
            AttendanceAgentResource::class,
            AttendanceIngestRequestResource::class,
            BranchResource::class,
            CompanyResource::class,
            DepartmentResource::class,
            OffboardingItemResource::class,
            PositionResource::class,
            RequestTypeResource::class,
            RoleResource::class,
            UserResource::class,
        ];
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // The panel is a place you visit from the app, so its logo and a menu entry lead back.
            ->homeUrl(fn (): string => route('dashboard'))
            ->navigationItems([
                NavigationItem::make('Kembali ke dashboard')
                    ->url(fn (): string => route('dashboard'))
                    ->icon(Heroicon::OutlinedArrowLeft)
                    ->sort(-1),
            ])
            // No ->login(): the panel signs in through the application's own login.
            ->colors([
                'primary' => Color::Amber,
            ])
            // Listed rather than discovered. Discovery scans these folders on every request, which
            // is slow on the Windows bind mount (about 1.4s of every page load here). A new
            // resource is one line below, in the same spirit as the workflow registry.
            ->resources($this->resources())
            ->pages($this->extraPages())
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                // The panel is not in the web group, so the entrance check is repeated here: the sign-in page too must
                // not be served on the public entrance (CORE-ACC-002).
                EnforceEntrance::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                AuthenticateAdminPanel::class,
                EnforceSessionAge::class,
                EnforceAccountState::class,
                EnforceTwoFactor::class,
                // After authentication, so the user is known and CompanyScope has a context.
                SetActiveCompany::class,
            ])
            // Table search, sorting, pagination and form actions are Livewire requests, which
            // only re-run the middleware listed here. Without this they would query with no
            // company context and ignore the company the administrator selected.
            ->persistentMiddleware([SetActiveCompany::class, EnforceTwoFactor::class]);
    }
}
