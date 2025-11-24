<?php

declare(strict_types=1);

use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => app('localization')->setLocale()], function (): void {

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

    Route::livewire('{slug}', 'pages::page')
        ->where('slug', '^(?!admin).*')
        ->name('page');
});
