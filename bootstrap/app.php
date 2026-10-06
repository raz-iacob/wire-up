<?php

declare(strict_types=1);

use App\Http\Controllers\StripeWebhookController;
use App\Http\Middleware\LocaleRedirect;
use App\Http\Middleware\OnlyAdmins;
use App\Http\Middleware\RequireTwoFactor;
use App\Http\Middleware\TrackUserAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Cashier\Http\Controllers\PaymentController;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        then: function (): void {
            Route::prefix('admin')->name('admin.')
                ->middleware(['web', OnlyAdmins::class, RequireTwoFactor::class])
                ->group(base_path('routes/admin.php'));

            Route::prefix('stripe')->name('cashier.')->group(function (): void {
                Route::get('payment/{id}', [PaymentController::class, 'show'])->name('payment');
                Route::post('webhook', StripeWebhookController::class)->name('webhook');
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectUsersTo(
            fn (Request $request): string => $request->user()?->canAccessAdmin()
                ? route('admin.dashboard')
                : route('home')
        );

        $middleware->appendToGroup('web', [
            LocaleRedirect::class,
            TrackUserAccess::class,
        ]);

        $middleware->preventRequestsDuringMaintenance(except: [
            'admin',
            'admin/*',
            'livewire/*',
            'livewire-*/*',
            'login',
            'stripe/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReportDuplicates();
    })->create();
