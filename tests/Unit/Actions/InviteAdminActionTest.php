<?php

declare(strict_types=1);

use App\Actions\InviteAdminAction;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AdminInvite;
use Illuminate\Support\Facades\Notification;

it('creates a new user with the given role and sends the invite notification', function (): void {

    $inviter = User::factory()->create();
    $role = Role::factory()->editor()->create();
    $action = resolve(InviteAdminAction::class);
    Notification::fake();

    $user = $action->handle($inviter, 'Test User', 'test@example.com', $role);

    expect($user->name)->toBe('Test User')
        ->and($user->email)->toBe('test@example.com')
        ->and($user->role_id)->toBe($role->id);

    Notification::assertSentTo($user, AdminInvite::class);
});
