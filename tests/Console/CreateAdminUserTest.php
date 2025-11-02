<?php

declare(strict_types=1);

use App\Console\Commands\CreateAdminUser;

it('creates an admin user', function (): void {
    $this->artisan(CreateAdminUser::class)
        ->expectsOutput('Create a new admin user')
        ->expectsQuestion('Enter name', 'Admin User')
        ->expectsQuestion('Enter email', 'admin@example.com')
        ->expectsQuestion('Please enter your desired password', 'password')
        ->expectsQuestion('Please confirm your password', 'password')
        ->expectsOutput('Admin user created successfully!')
        ->assertExitCode(0);
});

it('retries the password if confirmation does not match', function (): void {
    $this->artisan(CreateAdminUser::class)
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
