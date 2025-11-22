<?php

declare(strict_types=1);

use App\Http\Middleware\LocaleRedirect;
use App\Http\Middleware\OnlyAdmins;
use App\Http\Middleware\TrackUserAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        then: fn () => Route::prefix('admin')->name('admin.')
            ->middleware(['web', OnlyAdmins::class])
            ->group(base_path('routes/admin.php')),
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectUsersTo(
            fn (Request $request): string => $request->user()?->admin
                ? route('admin.dashboard')
                : route('home')
        );

        $middleware->appendToGroup('web', [
            LocaleRedirect::class,
            TrackUserAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
