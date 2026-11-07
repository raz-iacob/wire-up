<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Services\SettingsService;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class BackupFailed extends Notification
{
    public function __construct(public readonly string $reason, public readonly bool $savedLocally = false) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof AnonymousNotifiable && $notifiable->routes !== []) {
            return array_keys($notifiable->routes);
        }

        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brand = resolve(SettingsService::class)->brandName();

        return new MailMessage()
            ->from(config()->string('mail.from.address'), $brand)
            ->error()
            ->subject($this->subject())
            ->greeting(__('Hello,'))
            ->line($this->savedLocally
                ? __('The automatic backup of :app was saved on the server, but copying it off-site failed.', ['app' => $brand])
                : __('The automatic backup of :app did not finish, so no new backup was made.', ['app' => $brand]))
            ->line(__('Reason: :reason', ['reason' => mb_substr($this->reason, -600)]))
            ->action(__('Open Settings → Backups'), route('admin.settings-backups'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toSlackWebhook(): array
    {
        return [
            'text' => $this->subject(),
            'blocks' => [
                ['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => $this->subject(), 'emoji' => true]],
                ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], mb_substr($this->reason, -600))]],
                ['type' => 'actions', 'elements' => [[
                    'type' => 'button',
                    'text' => ['type' => 'plain_text', 'text' => __('Open backups'), 'emoji' => true],
                    'url' => route('admin.settings-backups'),
                ]]],
            ],
        ];
    }

    private function subject(): string
    {
        $brand = resolve(SettingsService::class)->brandName();

        return $this->savedLocally
            ? __('The off-site copy of the :app backup failed', ['app' => $brand])
            : __('The automatic backup of :app failed', ['app' => $brand]);
    }
}
