<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\WebhookEvent;
use App\Jobs\DeliverWebhook;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Submission;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Support\Str;

final readonly class Webhooks
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function dispatch(WebhookEvent $event, array $data): void
    {
        Webhook::query()
            ->where('active', true)
            ->get()
            ->filter(fn (Webhook $webhook): bool => $webhook->subscribesTo($event))
            ->each(fn (Webhook $webhook): WebhookDelivery => $this->queue($webhook, $event, $data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function queue(Webhook $webhook, WebhookEvent $event, array $data): WebhookDelivery
    {
        $delivery = $this->record($webhook, $event, $data);

        dispatch(new DeliverWebhook($delivery->id))->afterCommit();

        return $delivery;
    }

    public function test(Webhook $webhook): WebhookDelivery
    {
        $delivery = $this->record($webhook, WebhookEvent::PING, ['message' => __('A test from :site. Your webhook is working.', ['site' => resolve(SettingsService::class)->brandName()])]);

        dispatch_sync(new DeliverWebhook($delivery->id));

        return $delivery->refresh();
    }

    public function redeliver(WebhookDelivery $delivery): void
    {
        $delivery->update(['status_code' => null, 'response_excerpt' => null, 'error' => null, 'attempts' => 0, 'delivered_at' => null]);

        dispatch(new DeliverWebhook($delivery->id));
    }

    /**
     * @return array<string, mixed>
     */
    public function submissionData(Submission $submission): array
    {
        $submission->loadMissing('page');
        $metadata = is_array($submission->metadata) ? $submission->metadata : [];

        return [
            'id' => $submission->id,
            'type' => $submission->type,
            'form' => $submission->form_name,
            'name' => $submission->name,
            'email' => $submission->email,
            'phone' => $submission->phone,
            'subject' => $submission->subject,
            'message' => $submission->message,
            'fields' => array_values(array_filter(array_map(
                fn (mixed $field): ?array => is_array($field) && isset($field['label']) ? ['label' => $field['label'], 'value' => $field['value'] ?? null] : null,
                $metadata,
            ))),
            'page' => $submission->page === null ? null : [
                'id' => $submission->page->id,
                'title' => $submission->page->title,
                'url' => $submission->page->getSlug() === '' ? null : $submission->page->getUrl(),
            ],
            'locale' => $submission->locale,
            'country' => $submission->country,
            'created_at' => $submission->created_at->toAtomString(),
            'admin_url' => route('admin.inbox-show', $submission),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function orderData(Order $order): array
    {
        $order->loadMissing('items');
        $shop = resolve(ShopService::class);

        return [
            'reference' => $order->reference,
            'status' => $order->status->value,
            'currency' => $order->currency,
            'total' => $shop->formatMinor($order->total_amount, $order->currency),
            'amounts' => [
                'subtotal' => $order->subtotal_amount,
                'shipping' => $order->shipping_amount,
                'tax' => $order->tax_amount,
                'discount' => $order->discount_amount,
                'total' => $order->total_amount,
                'refunded' => $order->refunded_amount,
            ],
            'customer' => ['name' => $order->name, 'email' => $order->email],
            'shipping_address' => $order->shipping_address,
            'shipping_method' => $order->shipping_method,
            'items' => $order->items->map(fn (OrderItem $item): array => [
                'name' => $item->name,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'unit_amount' => $item->unit_amount,
                'line_amount' => $item->line_amount,
            ])->values()->all(),
            'paid_at' => $order->paid_at?->toAtomString(),
            'fulfilled_at' => $order->fulfilled_at?->toAtomString(),
            'cancelled_at' => $order->cancelled_at?->toAtomString(),
            'created_at' => $order->created_at->toAtomString(),
            'admin_url' => route('admin.orders-show', $order),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function record(Webhook $webhook, WebhookEvent $event, array $data): WebhookDelivery
    {
        $uuid = (string) Str::uuid();

        $delivery = $webhook->deliveries()->create([
            'uuid' => $uuid,
            'event' => $event->value,
            'payload' => [
                'id' => $uuid,
                'event' => $event->value,
                'created_at' => now()->toAtomString(),
                'site' => url('/'),
                'data' => $data,
            ],
        ]);

        $kept = $webhook->deliveries()->limit(Webhook::DELIVERIES_KEPT)->pluck('id');
        $webhook->deliveries()->whereNotIn('id', $kept)->delete();

        return $delivery;
    }
}
