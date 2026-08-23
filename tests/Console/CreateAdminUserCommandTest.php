<?php

declare(strict_types=1);

use App\Console\Commands\CreateAdminUserCommand;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

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

it('creates the admin user without prompting when every detail is passed as an option', function (): void {
    $this->artisan(CreateAdminUserCommand::class, [
        '--name' => 'Scripted Admin',
        '--email' => 'scripted@example.com',
        '--password' => 'secret-password',
    ])
        ->expectsOutput('Admin user created successfully!')
        ->assertExitCode(0);

    $user = User::query()->where('email', 'scripted@example.com')->firstOrFail();

    expect($user->name)->toBe('Scripted Admin')
        ->and($user->role_id)->toBe(Role::query()->where('key', 'owner')->firstOrFail()->id)
        ->and(Hash::check('secret-password', $user->password))->toBeTrue();
});

it('prompts only for the details that were not passed as options', function (): void {
    $this->artisan(CreateAdminUserCommand::class, ['--email' => 'partial@example.com'])
        ->expectsQuestion('Enter name', 'Partial Admin')
        ->expectsQuestion('Please enter your desired password', 'password')
        ->expectsQuestion('Please confirm your password', 'password')
        ->expectsOutput('Admin user created successfully!')
        ->assertExitCode(0);

    expect(User::query()->where('email', 'partial@example.com')->firstOrFail()->name)->toBe('Partial Admin');
});

it('reports invalid option values instead of creating a user', function (): void {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->artisan(CreateAdminUserCommand::class, [
        '--name' => 'Scripted Admin',
        '--email' => 'taken@example.com',
        '--password' => 'short',
    ])
        ->expectsOutputToContain('An account with that email address already exists.')
        ->expectsOutputToContain('The password must be at least 8 characters.')
        ->assertExitCode(1);

    expect(User::query()->where('name', 'Scripted Admin')->exists())->toBeFalse();
});
