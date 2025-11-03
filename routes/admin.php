<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::admin.dashboard')->name('dashboard');

Route::livewire('account/profile', 'pages::admin.account-profile')->name('account-profile');
