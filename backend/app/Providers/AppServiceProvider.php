<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Listeners\LogRoleChange;
use App\Models\AttendanceAgent;
use App\Models\OffboardingCase;
use App\Models\OffboardingCaseDepartment;
use App\Models\OffboardingCaseItem;
use App\Models\User;
use App\Policies\OffboardingCasePolicy;
use App\Support\Access\Entrance;
use App\Support\Attendance\WorkSchedule;
use App\Support\Features;
use App\Workflows\Definitions\LeaveRequestWorkflow;
use App\Workflows\Definitions\OffboardingCaseWorkflow;
use App\Workflows\WorkflowRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One per request: which entrance the request used (CORE-ACC-001).
        $this->app->singleton(Entrance::class);

        $this->registerWorkflows();
        $this->app->bind(WorkSchedule::class, fn (): WorkSchedule => WorkSchedule::current());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The proxies whose forwarded address and scheme, and entrance header, are believed (CORE-ACC-001).
        TrustProxies::at(config('entrance.trusted_proxies'));

        $this->configureDefaults();
        AboutCommand::add('HR Dashboard', fn (): array => ['Version' => config('app.version')]);
        $this->defineAttendanceGate();
        $this->defineOffboardingChecklistPolicies();
        $this->bindOffboardingRoutes();
        $this->configureApiRateLimiting();
        Event::listen(Login::class, fn (Login $event) => $event->user instanceof User
            ? $event->user->forceFill(['last_login_at' => now()])->saveQuietly()
            : null);
        $this->registerRoleAudit();
    }

    /**
     * The owner of a checklist item may belong to another company than the leaver (OFF-ITM-007), so these
     * routes find the case, group and item wherever they are; each action then asks the policy whether this
     * person may touch it, which is where company access is decided.
     */
    private function bindOffboardingRoutes(): void
    {
        Route::bind('offboardingCase', fn (string $value): OffboardingCase => OffboardingCase::acrossCompanies()->findOrFail($value));
        Route::bind('group', fn (string $value): OffboardingCaseDepartment => OffboardingCaseDepartment::query()->findOrFail($value));
        Route::bind('item', fn (string $value): OffboardingCaseItem => OffboardingCaseItem::query()->findOrFail($value));
    }

    /**
     * The checklist's groups and items are checked by the offboarding case's own policy, because who may
     * work them is a question about the case: its stage, who can see it, and who owns the department.
     */
    private function defineOffboardingChecklistPolicies(): void
    {
        Gate::policy(OffboardingCaseDepartment::class, OffboardingCasePolicy::class);
        Gate::policy(OffboardingCaseItem::class, OffboardingCasePolicy::class);
    }

    /**
     * Who may load scanner logs and map scanner IDs. The scanner is a group-level device, so this
     * needs a parent-wide account on top of the permission: a company-pinned HR admin must not be
     * able to file punches against, or read the unmapped IDs of, other companies.
     */
    protected function defineAttendanceGate(): void
    {
        Gate::define(
            'manage-attendance-device',
            fn (User $user): bool => Features::attendance()
                && $user->isParentWide()
                && $user->checkPermissionTo(Permission::ManageAttendance->value),
        );
    }

    /**
     * Throttle the ingest endpoint per token, so one runaway or abused agent cannot starve the rest
     * and a leaked token cannot be used to hammer the database (CORE-AUT-007). Falls back to the
     * caller's address when there is no token to key on.
     */
    protected function configureApiRateLimiting(): void
    {
        RateLimiter::for('attendance-ingest', function (Request $request): Limit {
            $agent = Auth::guard('sanctum')->user();
            $token = $agent instanceof AttendanceAgent ? $agent->currentAccessToken() : null;

            return Limit::perMinute(120)->by((string) ($token?->getKey() ?? $request->ip()));
        });
    }

    /**
     * Audit role assignments (CORE-RBA-007). Needs `events_enabled` in config/permission.php.
     */
    protected function registerRoleAudit(): void
    {
        Event::listen(RoleAttachedEvent::class, [LogRoleChange::class, 'handleAttached']);
        Event::listen(RoleDetachedEvent::class, [LogRoleChange::class, 'handleDetached']);
    }

    /**
     * Register every workflow definition with the registry.
     *
     * Definitions are listed here rather than auto-discovered, so the set of workflows the
     * application knows about is one readable list and an instance can never reference a
     * definition that was never wired up (CORE-WFL-001).
     */
    protected function registerWorkflows(): void
    {
        $this->app->singleton(WorkflowRegistry::class, fn (): WorkflowRegistry => new WorkflowRegistry([
            new LeaveRequestWorkflow,
            new OffboardingCaseWorkflow,
        ]));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
