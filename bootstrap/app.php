<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            \Illuminate\Support\Facades\Route::middleware('web')->group(__DIR__.'/../routes/auth.php');
            \Illuminate\Support\Facades\Route::middleware(['web', 'auth', 'mfa', 'staff'])
                ->prefix('portal')->name('portal.')->group(__DIR__.'/../routes/portal.php');
            \Illuminate\Support\Facades\Route::middleware(['web', 'auth', 'client'])
                ->prefix('my')->name('client.')->group(__DIR__.'/../routes/client.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->alias([
            'staff' => \App\Http\Middleware\EnsureStaff::class,
            'client' => \App\Http\Middleware\EnsureClient::class,
            'mfa' => \App\Http\Middleware\EnsureMfa::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
