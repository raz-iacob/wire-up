<?php

declare(strict_types=1);

use App\Actions\CreateSubmissionAction;
use App\Enums\ActivityEvent;
use App\Enums\OrderStatus;
use App\Enums\WebhookEvent;
use App\Jobs\DeliverWebhook;
use App\Models\Activity;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Page;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\PublicUrlGuard;
use App\Services\Webhooks;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('queues a delivery only for active webhooks that listen to the event', function (): void {
    Queue::fake();
    $listening = Webhook::factory()->create();
    Webhook::factory()->inactive()->create();
    Webhook::factory()->listeningTo([WebhookEvent::ORDER_PAID])->create();

    resolve(Webhooks::class)->dispatch(WebhookEvent::SUBMISSION_CREATED, ['name' => 'Ada']);

    $delivery = WebhookDelivery::query()->sole();

    expect($delivery->webhook_id)->toBe($listening->id)
        ->and($delivery->payload)->toMatchArray(['id' => $delivery->uuid, 'event' => 'submission.created', 'site' => url('/'), 'data' => ['name' => 'Ada']]);

    Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->deliveryId === $delivery->id);
});

it('sends new form messages and newsletter sign-ups with their details', function (): void {
    Queue::fake();
    Webhook::factory()->listeningTo([WebhookEvent::SUBMISSION_CREATED, WebhookEvent::NEWSLETTER_SUBSCRIBED])->create();
    $page = Page::factory()->create(['title' => 'Contact']);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'webhook-contact']);

    $submission = resolve(CreateSubmissionAction::class)->handle([
        'page_id' => $page->id,
        'type' => 'contact',
        'form_name' => 'Quote request',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'message' => 'Hello',
        'metadata' => ['budget' => ['label' => 'Budget', 'value' => '£500'], 'newsletter' => 'not a field'],
    ]);
    resolve(CreateSubmissionAction::class)->handle(['type' => 'newsletter', 'email' => 'news@example.com', 'page_id' => Page::factory()->create()->id]);

    [$newsletter, $contact] = WebhookDelivery::query()->latest('id')->get()->all();

    expect($contact->event)->toBe('submission.created')
        ->and($contact->payload['data'])->toMatchArray([
            'id' => $submission->id,
            'form' => 'Quote request',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'fields' => [['label' => 'Budget', 'value' => '£500']],
            'page' => ['id' => $page->id, 'title' => 'Contact', 'url' => $page->getUrl()],
            'admin_url' => route('admin.inbox-show', $submission),
        ])
        ->and($newsletter->event)->toBe('newsletter.subscribed')
        ->and($newsletter->payload['data']['page']['url'])->toBeNull();
});

it('waits for the database to commit before sending anything', function (): void {
    Http::fake();
    Webhook::factory()->create();

    rescue(fn () => DB::transaction(function (): void {
        resolve(Webhooks::class)->dispatch(WebhookEvent::SUBMISSION_CREATED, []);

        throw new RuntimeException('rolled back');
    }), report: false);

    Http::assertNothingSent();
    expect(WebhookDelivery::query()->exists())->toBeFalse();

    DB::transaction(fn () => resolve(Webhooks::class)->dispatch(WebhookEvent::SUBMISSION_CREATED, []));

    Http::assertSentCount(1);
});

it('sends an order each time its status really changes', function (): void {
    Queue::fake();
    Webhook::factory()->listeningTo([WebhookEvent::ORDER_PAID, WebhookEvent::ORDER_REFUNDED, WebhookEvent::ORDER_CANCELLED, WebhookEvent::ORDER_FULFILLED])->create();
    $order = Order::factory()->create(['email' => 'sam@example.com', 'name' => 'Sam Buyer', 'total_amount' => 4500]);
    OrderItem::factory()->create(['order_id' => $order->id, 'name' => 'Desk lamp', 'quantity' => 3, 'unit_amount' => 1500, 'line_amount' => 4500]);

    $order->update(['status' => OrderStatus::PROCESSING]);
    $order->update(['status' => OrderStatus::PAID, 'paid_at' => now()]);
    $order->update(['status' => OrderStatus::PAID, 'refunded_amount' => 0]);
    $order->update(['status' => OrderStatus::REFUNDED, 'refunded_amount' => 4500]);

    $events = WebhookDelivery::query()->oldest('id')->pluck('event')->all();
    $paid = WebhookDelivery::query()->oldest('id')->first();

    expect($events)->toBe(['order.paid', 'order.refunded'])
        ->and($paid?->payload['data'])->toMatchArray([
            'reference' => $order->reference,
            'status' => 'paid',
            'total' => '$45.00',
            'customer' => ['name' => 'Sam Buyer', 'email' => 'sam@example.com'],
            'items' => [['name' => 'Desk lamp', 'sku' => null, 'quantity' => 3, 'unit_amount' => 1500, 'line_amount' => 4500]],
            'admin_url' => route('admin.orders-show', $order),
        ]);
});

it('signs and delivers the payload, and remembers it was delivered', function (): void {
    Queue::fake();
    Http::fake(['hooks.example.com/*' => Http::response('ok', 200)]);
    $webhook = Webhook::factory()->create(['url' => 'https://hooks.example.com/inbox']);
    $delivery = resolve(Webhooks::class)->queue($webhook, WebhookEvent::SUBMISSION_CREATED, ['name' => 'Ada']);

    new DeliverWebhook($delivery->id)->withFakeQueueInteractions()->handle(resolve(PublicUrlGuard::class));

    Http::assertSent(function (Request $request) use ($delivery, $webhook): bool {
        $timestamp = $request->header('X-Wire-Up-Timestamp')[0];

        return $request->url() === 'https://hooks.example.com/inbox'
            && $request->header('X-Wire-Up-Event')[0] === 'submission.created'
            && $request->header('X-Wire-Up-Delivery')[0] === $delivery->uuid
            && $request->header('User-Agent')[0] === 'Wire Up webhooks'
            && $request->header('X-Wire-Up-Signature')[0] === 'sha256='.hash_hmac('sha256', $timestamp.'.'.$request->body(), $webhook->secret)
            && json_decode($request->body(), true)['data'] === ['name' => 'Ada'];
    });

    expect($delivery->refresh()->succeeded())->toBeTrue()
        ->and($delivery->status_code)->toBe(200)
        ->and($delivery->attempts)->toBe(1)
        ->and($webhook->refresh()->last_delivered_at)->not->toBeNull()
        ->and($webhook->last_error)->toBeNull();

    $again = new DeliverWebhook($delivery->id)->withFakeQueueInteractions();
    $again->handle(resolve(PublicUrlGuard::class));
    new DeliverWebhook(999999)->withFakeQueueInteractions()->handle(resolve(PublicUrlGuard::class));

    Http::assertSentCount(1);
});

it('retries a failed delivery with growing pauses, then gives up and logs it', function (): void {
    Queue::fake();
    Http::fake(['hooks.example.com/*' => Http::sequence()->push('nope', 500)->pushFailedConnection('refused')->push('nope', 503)]);
    $webhook = Webhook::factory()->create(['url' => 'https://hooks.example.com/inbox', 'name' => 'CRM']);
    $delivery = resolve(Webhooks::class)->queue($webhook, WebhookEvent::SUBMISSION_CREATED, []);
    $guard = resolve(PublicUrlGuard::class);

    $first = new DeliverWebhook($delivery->id)->withFakeQueueInteractions();
    $first->handle($guard);
    $first->assertReleased(60);

    expect($delivery->refresh()->error)->toBe('The endpoint answered with HTTP 500.')
        ->and($webhook->refresh()->last_status)->toBe(500);

    $second = new DeliverWebhook($delivery->id)->withFakeQueueInteractions();
    $second->job->attempts = 2;
    $second->handle($guard);
    $second->assertReleased(300);

    expect($delivery->refresh()->error)->toContain('refused');

    $last = new DeliverWebhook($delivery->id)->withFakeQueueInteractions();
    $last->job->attempts = 5;
    $last->handle($guard);
    $last->assertFailed();
    $last->failed(null);

    $activity = Activity::query()->sole();

    expect($activity->event)->toBe(ActivityEvent::WEBHOOK_FAILED)
        ->and($activity->subject_label)->toBe('CRM')
        ->and($activity->properties)->toMatchArray(['event' => 'submission.created', 'reason' => 'The endpoint answered with HTTP 503.']);
});

it('refuses to call an address on a private network', function (): void {
    Queue::fake();
    Http::fake();
    $webhook = Webhook::factory()->create(['url' => 'https://127.0.0.1/hook']);
    $delivery = resolve(Webhooks::class)->queue($webhook, WebhookEvent::SUBMISSION_CREATED, []);

    $job = new DeliverWebhook($delivery->id)->withFakeQueueInteractions();
    $job->handle(resolve(PublicUrlGuard::class));

    $job->assertFailed();
    Http::assertNothingSent();
    expect($delivery->refresh()->error)->toBe('The address points to a private network, so it was not called.');
});

it('keeps only the latest deliveries for each webhook', function (): void {
    Queue::fake();
    $webhook = Webhook::factory()->create();
    WebhookDelivery::factory()->count(Webhook::DELIVERIES_KEPT)->for($webhook)->create();

    $newest = resolve(Webhooks::class)->queue($webhook, WebhookEvent::SUBMISSION_CREATED, []);

    expect($webhook->deliveries()->count())->toBe(Webhook::DELIVERIES_KEPT)
        ->and($webhook->deliveries()->first()?->id)->toBe($newest->id);
});
