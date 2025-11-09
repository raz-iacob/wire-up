<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\URL;

it('may verify email', function (): void {
    $user = User::factory()->create([
        'email_verified_at' => null,
        'admin' => true,
    ]);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->getKey(), 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user)
        ->get($verificationUrl);

    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();

    $response->assertRedirect(route('admin.dashboard', absolute: false).'?verified=1');
});

it('redirects to dashboard if admin and already verified', function (): void {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'admin' => true,
    ]);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->getKey(), 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user)
        ->get($verificationUrl);

    $response->assertRedirect(route('admin.dashboard', absolute: false).'?verified=1');
});

it('redirects to home if already verified', function (): void {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'admin' => false,
    ]);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->getKey(), 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user)
        ->get($verificationUrl);

    $response->assertRedirect(route('home', absolute: false).'?verified=1');
});

it('requires valid signature', function (): void {
    $user = User::factory()->create([
        'email_verified_at' => null,
    ]);

    $invalidUrl = route('verification.verify', [
        'id' => $user->getKey(),
        'hash' => sha1($user->email),
    ]);

    $response = $this->actingAs($user)
        ->get($invalidUrl);

    $response->assertForbidden();
});
