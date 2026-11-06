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
        private readonly bool $siteIsDown = true,
        private readonly ?string $rolledBackTo = null,
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
            ->line(match (true) {
                $this->siteIsDown => __('The update stopped at the step ":step" and your site was left in maintenance mode.', ['step' => $this->step]),
                $this->rolledBackTo !== null => __('The update stopped at the step ":step", so your site was put back on :version and is live again.', ['step' => $this->step, 'version' => $this->rolledBackTo]),
                default => __('The update stopped at the step ":step" before your site went into maintenance mode, so it is still live.', ['step' => $this->step]),
            });

        if ($this->output !== '') {
            $mail->line(__('Last output:'))->line(mb_substr($this->output, -600));
        }

        return $mail
            ->line($this->siteIsDown
                ? __('Review the error, fix the issue, then bring the site back with: php artisan up')
                : __('Review the error and fix the issue, then start the update again.'))
            ->action(__('Open Settings → Updates'), route('admin.settings-updates'));
    }
}
