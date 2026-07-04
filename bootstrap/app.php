<?php

use App\Http\Middleware\Idempotency;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\MinAppVersion;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Web SPA (first-party) auth: requests whose Origin/Referer match
        // SANCTUM_STATEFUL_DOMAINS get session + CSRF-cookie handling on the
        // `api` group; everyone else (mobile / third-party) falls through to
        // bearer-token auth. See config/cors.php (supports_credentials) and the
        // auto-registered GET /sanctum/csrf-cookie route.
        $middleware->statefulApi();

        $middleware->alias([
            'identify.tenant' => IdentifyTenant::class,
            'min.app.version' => MinAppVersion::class,
            'idempotency' => Idempotency::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
