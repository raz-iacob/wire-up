<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::admin.dashboard')->name('dashboard');

Route::livewire('account', 'pages::admin.account-profile')->name('account-profile');
Route::livewire('account/password', 'pages::admin.account-password')->name('account-password');
Route::livewire('account/appearance', 'pages::admin.account-appearance')->name('account-appearance');

Route::livewire('pages', 'pages::admin.pages-index')->name('pages-index');
Route::livewire('pages/{page}/edit', 'pages::admin.pages-edit')->name('pages-edit');

Route::livewire('users', 'pages::admin.users-index')->name('users-index');
Route::livewire('users/{user}/edit', 'pages::admin.users-edit')->name('users-edit');
