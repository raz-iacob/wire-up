<?php

declare(strict_types=1);

use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\SessionController;
use App\Http\Middleware\RedirectHomepageSlug;
use App\Services\SettingsService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => resolve('localization')->setLocale()], function (): void {

    Route::livewire('/', 'pages::home')->name('home');

    Route::middleware('guest')->group(function (): void {
        Route::livewire('login', 'pages::auth.login')->name('login');
        Route::livewire('register', 'pages::auth.register')->name('register');
        Route::livewire('forgot-password', 'pages::auth.forgot-password')->name('password.request');
        Route::livewire('reset-password/{token}', 'pages::auth.reset-password')->name('password.reset');
    });

    Route::middleware('auth')->group(function (): void {

        Route::get('verify-email/{id}/{hash}', [EmailVerificationController::class, 'update'])
            ->name('verification.verify')
            ->middleware(['signed', 'throttle:6,1']);

        Route::post('logout', [SessionController::class, 'destroy'])->name('logout');
    });
});

Route::get('img/{options}/{path}', [ImageController::class, 'show'])
    ->where('path', '.*')
    ->name('image.show');

Route::get('robots.txt', function (): Response {
    $body = SettingsService::current()->noindex()
        ? "User-agent: *\nDisallow: /\n"
        : "User-agent: *\nDisallow:\n";

    return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
})->name('robots');

Route::group(['prefix' => resolve('localization')->setLocale()], function (): void {
    Route::livewire('{slug}', 'pages::page')
        ->where('slug', '^(?!admin).*')
        ->middleware(RedirectHomepageSlug::class)
        ->name('page');
});
