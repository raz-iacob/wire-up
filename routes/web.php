<?php

declare(strict_types=1);

use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::home')->name('home');

Route::middleware('guest')->group(function (): void {
    Route::livewire('login', 'pages::login')->name('login');
    Route::livewire('register', 'pages::register')->name('register');
    Route::livewire('forgot-password', 'pages::forgot-password')->name('password.request');
    Route::livewire('reset-password/{token}', 'pages::reset-password')->name('password.reset');
});

Route::middleware('auth')->group(function (): void {

    Route::post('logout', [SessionController::class, 'destroy'])->name('logout');
});
