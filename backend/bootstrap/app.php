<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetActiveCompany;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // SubstituteBindings is pulled out of its default slot and re-added *after*
        // SetActiveCompany. The order is load-bearing: route model binding resolves
        // {employee}, {leaveRequest} and friends through an ordinary Eloquent query, so if the
        // active company has not been established yet, CompanyScope has nothing to filter on
        // and a record from another company binds successfully. The policies also re-check the
        // company, but relying on that alone would make tenancy a matter of every policy
        // remembering — the scope should be doing the work.
        $middleware->web(remove: [SubstituteBindings::class]);

        $middleware->web(append: [
            HandleAppearance::class,
            SetActiveCompany::class,
            SubstituteBindings::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
