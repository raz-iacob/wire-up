<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use App\Services\SettingsService;
use App\Services\ShopService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OrderConfirmation extends Notification implements ShouldQueue
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
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brand = resolve(SettingsService::class)->brandName();
        $replyTo = SettingsService::current()->contactEmail();

        $mail = new MailMessage()
            ->from(config()->string('mail.from.address'), $brand)
            ->subject(__('Your :brand order :reference', ['brand' => $brand, 'reference' => $this->order->reference]))
            ->markdown('mail.order-confirmation', [
                'order' => $this->order->loadMissing('items'),
                'shop' => resolve(ShopService::class),
            ]);

        if ($replyTo !== '') {
            $mail->replyTo($replyTo);
        }

        return $mail;
    }
}
