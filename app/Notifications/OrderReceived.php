<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\SettingsService;
use App\Services\ShopService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OrderReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order)
    {
        $this->afterCommit = true;
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
            ->subject($this->subject())
            ->markdown('mail.order-received', [
                'order' => $this->order->loadMissing('items'),
                'shop' => resolve(ShopService::class),
                'viewUrl' => route('admin.orders-show', $this->order),
            ]);

        if (is_string($this->order->email) && $this->order->email !== '') {
            $mail->replyTo($this->order->email);
        }

        return $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSlackWebhook(): array
    {
        $total = resolve(ShopService::class)->formatMinor($this->order->total_amount, $this->order->currency);
        $customer = $this->order->name ?: ($this->order->email ?: __('A guest'));
        $items = $this->order->loadMissing('items')->items
            ->map(fn (OrderItem $item): string => '• '.$this->slackEscape($item->name).' × '.$item->quantity)
            ->implode("\n");

        return [
            'text' => $this->subject(),
            'blocks' => [
                ['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => $this->subject(), 'emoji' => true]],
                ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => '*'.$this->slackEscape($customer).'* · '.$total."\n".$items]],
                ['type' => 'actions', 'elements' => [[
                    'type' => 'button',
                    'text' => ['type' => 'plain_text', 'text' => __('View order'), 'emoji' => true],
                    'url' => route('admin.orders-show', $this->order),
                ]]],
            ],
        ];
    }

    private function subject(): string
    {
        return __('New order :reference', ['reference' => $this->order->reference]);
    }

    private function slackEscape(string $value): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $value);
    }
}
