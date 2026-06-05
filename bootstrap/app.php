<?php

use App\Http\Middleware\AuthenticateExternalInstitutionIngest;
use App\Http\Middleware\EnsureMustChangePassword;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'external.institution.ingest' => AuthenticateExternalInstitutionIngest::class,
            'must.change.password' => EnsureMustChangePassword::class,
        ]);

        $middleware->web(append: [
            EnsureMustChangePassword::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
