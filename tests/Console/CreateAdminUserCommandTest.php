<?php

declare(strict_types=1);

use App\Console\Commands\CreateAdminUserCommand;
use App\Models\Role;
use App\Models\User;

it('creates an admin user holding the owner role on a fresh install', function (): void {
    $this->artisan(CreateAdminUserCommand::class)
        ->expectsOutput('Create a new admin user')
        ->expectsQuestion('Enter name', 'Admin User')
        ->expectsQuestion('Enter email', 'admin@example.com')
        ->expectsQuestion('Please enter your desired password', 'password')
        ->expectsQuestion('Please confirm your password', 'password')
        ->expectsOutput('Admin user created successfully!')
        ->assertExitCode(0);

    $user = User::query()->where('email', 'admin@example.com')->firstOrFail();

    expect($user->role_id)->toBe(Role::query()->where('key', 'owner')->firstOrFail()->id)
        ->and($user->canAccessAdmin())->toBeTrue();
});

it('retries the password if confirmation does not match', function (): void {
    $this->artisan(CreateAdminUserCommand::class)
        ->expectsOutput('Create a new admin user')
        ->expectsQuestion('Enter name', 'Admin User')
        ->expectsQuestion('Enter email', 'admin@example.com')
        ->expectsQuestion('Please enter your desired password', 'password')
        ->expectsQuestion('Please confirm your password', 'wrong-password')
        ->expectsOutputToContain('Passwords do not match')
        ->expectsQuestion('Please enter your desired password', 'password')
        ->expectsQuestion('Please confirm your password', 'password')
        ->expectsOutput('Admin user created successfully!')
        ->assertExitCode(0);
});
