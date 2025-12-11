<?php

declare(strict_types=1);

use App\Actions\InviteAdminAction;
use App\Models\User;
use App\Notifications\AdminInvite;
use Illuminate\Support\Facades\Notification;

it('creates a new user and sends the invite notification', function (): void {

    $inviter = User::factory()->create();
    $action = resolve(InviteAdminAction::class);
    Notification::fake();

    $user = $action->handle($inviter, 'Test User', 'test@example.com');

    expect(User::query()->count())->toBe(2)
        ->and($user->name)->toBe('Test User')
        ->and($user->email)->toBe('test@example.com')
        ->and($user->admin)->toBeTrue();

    Notification::assertSentTo($user, AdminInvite::class);
});
