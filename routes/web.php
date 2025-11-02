<?php

declare(strict_types=1);

use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::home')->name('home');

Route::middleware('guest')->group(function (): void {
    Route::livewire('login', 'pages::auth.login')->name('login');
    Route::livewire('register', 'pages::auth.register')->name('register');
    Route::livewire('forgot-password', 'pages::auth.forgot-password')->name('password.request');
    Route::livewire('reset-password/{token}', 'pages::auth.reset-password')->name('password.reset');
});

Route::middleware('auth')->group(function (): void {

    Route::post('logout', [SessionController::class, 'destroy'])->name('logout');
});
