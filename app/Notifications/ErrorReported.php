<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Services\SettingsService;
use App\Services\UpdateService;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use Throwable;

final class ErrorReported extends Notification
{
    public readonly string $type;

    public readonly string $message;

    public readonly string $location;

    public readonly string $address;

    /**
     * @var list<string>
     */
    public readonly array $trace;

    /**
     * @var array<string, string>
     */
    public readonly array $context;

    public function __construct(Throwable $exception)
    {
        $this->type = $exception::class;
        $this->message = Str::limit(mb_trim($exception->getMessage()), 1000);
        $this->location = $this->relative($exception->getFile()).':'.$exception->getLine();
        $this->address = app()->runningInConsole() && request()->route() === null ? __('Command line or queue') : request()->method().' '.request()->url();
        $this->trace = array_map(
            fn (array $frame): string => $this->relative($frame['file']).':'.($frame['line'] ?? ''),
            array_slice(array_filter($exception->getTrace(), fn (array $frame): bool => isset($frame['file'])), 0, 10),
        );
        $this->context = [
            __('Where') => $this->location,
            __('Address') => $this->address,
            __('Signed in') => auth()->check() ? __('User #:id', ['id' => auth()->id()]) : __('No'),
            __('Version') => 'Wire-Up '.(resolve(UpdateService::class)->currentVersion() ?? '?').' · PHP '.PHP_VERSION.' · '.app()->environment(),
            __('Time') => now()->toDateTimeString(),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? array_keys($notifiable->routes) : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = new MailMessage()
            ->from(config()->string('mail.from.address'), resolve(SettingsService::class)->brandName())
            ->error()
            ->subject($this->subject())
            ->line('**'.class_basename($this->type).':** '.$this->message);

        foreach ($this->context as $label => $value) {
            $mail->line($label.': '.$value);
        }

        return $mail->line(__('Stack trace:'))->lines($this->trace);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSlackWebhook(): array
    {
        $escape = fn (string $text): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);
        $details = collect($this->context)->map(fn (string $value, string $label): string => '*'.$label.':* '.$escape($value))->implode("\n");

        return [
            'text' => $this->subject(),
            'blocks' => [
                ['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => Str::limit($this->subject(), 150), 'emoji' => true]],
                ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => '*'.$escape($this->type).'*'."\n".$escape($this->message)]],
                ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => $details]],
                ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => "```\n".$escape(implode("\n", $this->trace))."\n```"]],
            ],
        ];
    }

    private function subject(): string
    {
        return __('Error on :site: :type', ['site' => resolve(SettingsService::class)->brandName(), 'type' => class_basename($this->type)]);
    }

    private function relative(string $path): string
    {
        return Str::after($path, base_path().DIRECTORY_SEPARATOR);
    }
}
