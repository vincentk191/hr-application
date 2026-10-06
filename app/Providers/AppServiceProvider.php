<?php

namespace App\Providers;

use App\Workflows\Definitions\OffboardingCaseWorkflow;
use App\Workflows\WorkflowRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerWorkflows();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
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
