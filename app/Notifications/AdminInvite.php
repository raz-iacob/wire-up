<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class AdminInvite extends Notification
{
    use Queueable;

    public function __construct(
        public string $inviterName,
        public string $token
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->buildMailMessage($this->acceptInviteUrl($notifiable));
    }

    private function buildMailMessage(string $url): MailMessage
    {
        $appName = config()->string('app.name');

        return new MailMessage()
            ->subject(__('You have been invited to join :app_name', ['app_name' => $appName]))
            ->greeting(__('Hello,'))
            ->line($this->inviterName.' '.__('has invited you to join the :app_name team.', ['app_name' => $appName]))
            ->action(__('Accept Invite'), $url)
            ->line(__('If you did not request an account, no further action is required.'));
    }

    private function acceptInviteUrl(object $notifiable): string
    {
        return url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->email,
        ], false));
    }
}
