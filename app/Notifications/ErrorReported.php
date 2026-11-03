<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ErrorLog;
use App\Services\SettingsService;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

final class ErrorReported extends Notification
{
    public function __construct(public readonly ErrorLog $error) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? array_keys($notifiable->routes) : ['mail'];
    }

    /**
     * @return array<string, string>
     */
    public function details(): array
    {
        $context = $this->error->context;

        return [
            __('Where') => $this->error->file.':'.$this->error->line,
            __('Address') => $this->error->address ?? __('Command line or queue'),
            __('Signed in') => $this->error->user_id === null ? __('No') : __('User #:id', ['id' => $this->error->user_id]),
            __('Occurrences') => (string) $this->error->occurrences,
            __('Version') => 'Wire-Up '.($context['version'] ?? '?').' · PHP '.($context['php'] ?? '?').' · '.($context['environment'] ?? '?'),
            __('Time') => $this->error->last_seen_at->toDateTimeString(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = new MailMessage()
            ->from(config()->string('mail.from.address'), resolve(SettingsService::class)->brandName())
            ->error()
            ->subject($this->subject())
            ->line('**'.class_basename($this->error->type).':** '.Str::limit($this->error->message, 1000));

        foreach ($this->details() as $label => $value) {
            $mail->line($label.': '.$value);
        }

        if ($this->error->exists) {
            $mail->action(__('Open the error log'), $this->url());
        }

        return $mail->line(__('Stack trace:'))->lines(array_slice($this->error->trace, 0, 10));
    }

    /**
     * @return array<string, mixed>
     */
    public function toSlackWebhook(): array
    {
        $escape = fn (string $text): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);
        $details = collect($this->details())->map(fn (string $value, string $label): string => '*'.$label.':* '.$escape($value))->implode("\n");

        $blocks = [
            ['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => Str::limit($this->subject(), 150), 'emoji' => true]],
            ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => '*'.$escape($this->error->type).'*'."\n".$escape(Str::limit($this->error->message, 1000))]],
            ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => $details]],
            ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => "```\n".$escape(implode("\n", array_slice($this->error->trace, 0, 10)))."\n```"]],
        ];

        if ($this->error->exists) {
            $blocks[] = ['type' => 'actions', 'elements' => [[
                'type' => 'button',
                'text' => ['type' => 'plain_text', 'text' => __('Open the error log'), 'emoji' => true],
                'url' => $this->url(),
            ]]];
        }

        return ['text' => $this->subject(), 'blocks' => $blocks];
    }

    private function subject(): string
    {
        return __('Error on :site: :type', ['site' => resolve(SettingsService::class)->brandName(), 'type' => class_basename($this->error->type)]);
    }

    private function url(): string
    {
        return route('admin.settings-errors', ['error' => $this->error->id]);
    }
}
