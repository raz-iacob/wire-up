<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\AdminInvite;
use Illuminate\Support\Facades\Notification;

it('sends an invite notification', function (): void {

    $invitee = User::factory()->create([
        'name' => 'New Admin User',
        'email' => 'admin@example.com',
        'admin' => true,
    ]);

    $inviter = User::factory()->create([
        'name' => 'Admin User',
        'email' => 'inviter@example.com',
        'admin' => true,
    ]);

    $token = 'test-set-token';

    Notification::fake();

    $invitee->notify(new AdminInvite($inviter->name, $token));

    Notification::assertSentTo(
        $invitee,
        AdminInvite::class,
        function (AdminInvite $notification, array $channels) use ($invitee, $inviter, $token): true {
            $this->assertEquals(['mail'], $channels);

            $mailMessage = $notification->toMail($invitee);
            $setPassewordUrl = url(route('password.reset', [
                'token' => $token,
                'email' => $invitee->email,
            ], false));

            $this->assertEquals(__('You have been invited to join :app_name', ['app_name' => config()->string('app.name')]), $mailMessage->subject);
            $this->assertStringContainsString($inviter->name.' '.__('has invited you to join the :app_name team', ['app_name' => config()->string('app.name')]), $mailMessage->introLines[0]);
            $this->assertEquals($setPassewordUrl, $mailMessage->actionUrl);
            $this->assertStringContainsString('If you did not request an account, no further action is required.', $mailMessage->outroLines[0]);

            return true;
        }
    );
});
