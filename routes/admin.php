<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::admin.dashboard')->name('dashboard');

Route::livewire('account', 'pages::admin.account-profile')->name('account-profile');
Route::livewire('account/password', 'pages::admin.account-password')->name('account-password');
Route::livewire('account/appearance', 'pages::admin.account-appearance')->name('account-appearance');

Route::livewire('pages', 'pages::admin.pages-index')->name('pages-index');
Route::livewire('pages/{page}/edit', 'pages::admin.pages-edit')->name('pages-edit');
Route::livewire('pages/{page}/preview/{token}', 'pages::admin.page-preview')->name('pages-preview');

Route::livewire('records/{recordType}', 'pages::admin.records-index')->name('records-index');
Route::livewire('records/{recordType}/{record}/edit', 'pages::admin.records-edit')->name('records-edit');
Route::livewire('records/{recordType}/{record}/preview/{token}', 'pages::admin.record-preview')->name('records-preview');

Route::livewire('categories', 'pages::admin.categories-index')->name('categories-index');
Route::livewire('categories/{category}/edit', 'pages::admin.categories-edit')->name('categories-edit');

Route::livewire('inbox', 'pages::admin.inbox-index')->name('inbox-index');
Route::livewire('inbox/{submission}', 'pages::admin.inbox-show')->name('inbox-show');

Route::livewire('users', 'pages::admin.users-index')->name('users-index');
Route::livewire('users/{user}/edit', 'pages::admin.users-edit')->name('users-edit');

Route::redirect('settings', 'admin/settings/general');
Route::livewire('settings/general', 'pages::admin.settings-general')->name('settings-general');
Route::livewire('settings/content-types', 'pages::admin.record-types-index')->name('record-types-index');
Route::livewire('settings/identity', 'pages::admin.settings-identity')->name('settings-identity');
Route::livewire('settings/design', 'pages::admin.settings-design')->name('settings-design');
Route::livewire('settings/menus', 'pages::admin.settings-menus')->name('settings-menus');
Route::livewire('settings/social', 'pages::admin.settings-social')->name('settings-social');
Route::livewire('settings/integrations', 'pages::admin.settings-integrations')->name('settings-integrations');
