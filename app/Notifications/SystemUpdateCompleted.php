<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Services\SettingsService;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class SystemUpdateCompleted extends Notification
{
    public function __construct(
        private readonly string $tag,
        private readonly bool $successful,
        private readonly string $step = '',
        private readonly string $output = '',
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
        $brand = resolve(SettingsService::class)->brandName();

        $mail = new MailMessage()
            ->from(config()->string('mail.from.address'), $brand)
            ->greeting(__('Hello,'));

        if ($this->successful) {
            return $mail
                ->subject(__(':app has been updated to :tag', ['app' => $brand, 'tag' => $this->tag]))
                ->line(__('The update to :tag completed successfully and your site is live again.', ['tag' => $this->tag]))
                ->action(__('Open Settings → Updates'), route('admin.settings-updates'));
        }

        $mail = $mail
            ->error()
            ->subject(__(':app update to :tag failed', ['app' => $brand, 'tag' => $this->tag]))
            ->line(__('The update stopped at the step ":step" and your site was left in maintenance mode.', ['step' => $this->step]));

        if ($this->output !== '') {
            $mail->line(__('Last output:'))->line(mb_substr($this->output, -600));
        }

        return $mail
            ->line(__('Review the error, fix the issue, then bring the site back with: php artisan up'))
            ->action(__('Open Settings → Updates'), route('admin.settings-updates'));
    }
}
