<?php

declare(strict_types=1);

use App\Actions\InviteAdminAction;
use App\Actions\SendAdminInviteAction;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AdminInvite;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('creates a new user with the given role and sends the invite notification', function (): void {

    $inviter = User::factory()->create();
    $role = Role::factory()->editor()->create();
    $action = resolve(InviteAdminAction::class);
    Notification::fake();

    ['user' => $user, 'status' => $status] = $action->handle($inviter, 'Test User', 'test@example.com', $role);

    expect($user->name)->toBe('Test User')
        ->and($user->email)->toBe('test@example.com')
        ->and($user->role_id)->toBe($role->id)
        ->and($status)->toBe(Password::RESET_LINK_SENT)
        ->and($user->refresh()->invited_at)->not->toBeNull();

    Notification::assertSentTo($user, AdminInvite::class);
});

it('creates the user but sends nothing when no email provider can deliver', function (): void {

    config()->set('mail.default', 'log');

    $inviter = User::factory()->create();
    $role = Role::factory()->editor()->create();
    Notification::fake();

    ['user' => $user, 'status' => $status] = resolve(InviteAdminAction::class)
        ->handle($inviter, 'Test User', 'test@example.com', $role);

    expect($status)->toBe(SendAdminInviteAction::NOT_CONFIGURED)
        ->and($user->refresh()->invited_at)->toBeNull();

    Notification::assertNotSentTo($user, AdminInvite::class);
});

it('still invites when the site smtp settings are filled in', function (): void {

    config()->set('mail.default', 'log');
    config()->set('site.mail_host', 'smtp.example.com');
    config()->set('site.mail_username', 'postmaster');
    config()->set('site.mail_password', 'secret');
    config()->set('site.mail_from_address', 'hello@example.com');

    $inviter = User::factory()->create();
    $role = Role::factory()->editor()->create();
    Notification::fake();

    ['user' => $user, 'status' => $status] = resolve(InviteAdminAction::class)
        ->handle($inviter, 'Test User', 'test@example.com', $role);

    expect($status)->toBe(Password::RESET_LINK_SENT);

    Notification::assertSentTo($user, AdminInvite::class);
});
