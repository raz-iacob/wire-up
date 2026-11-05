<?php

declare(strict_types=1);

use App\Enums\WebhookEvent;
use App\Jobs\DeliverWebhook;
use App\Models\Role;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

it('opens for people who can see the settings, under Integrations', function (): void {
    $this->actingAs(User::factory()->member()->create(['active' => true]));
    $this->get(route('admin.settings-webhooks'))->assertRedirect();

    $this->actingAsAdmin();

    $this->get(route('admin.settings-webhooks'))
        ->assertOk()
        ->assertSee('No webhooks yet.')
        ->assertSeeInOrder([route('admin.settings-integrations'), 'App Integrations', route('admin.settings-webhooks'), 'Webhooks']);
});

it('adds a webhook and shows its signing secret once', function (): void {
    $this->actingAsAdmin();

    $component = Livewire::test('pages::admin.settings-webhooks')
        ->call('add')
        ->assertSet('events', ['submission.created'])
        ->set('name', ' Zapier ')
        ->set('url', 'https://hooks.zapier.com/catch/1')
        ->set('events', ['submission.created', 'order.paid'])
        ->call('save')
        ->assertHasNoErrors();

    $webhook = Webhook::query()->sole();

    expect($webhook->name)->toBe('Zapier')
        ->and($webhook->events)->toBe(['submission.created', 'order.paid'])
        ->and($webhook->secret)->toStartWith('whsec_');

    $component->assertSet('newSecret', $webhook->secret)
        ->assertSee($webhook->secret)
        ->call('closeSecret')
        ->assertSet('newSecret', null)
        ->assertSee('hooks.zapier.com/catch/1');
});

it('checks the address and the events', function (array $fields, array $errors): void {
    $this->actingAsAdmin();

    $component = Livewire::test('pages::admin.settings-webhooks')->call('add');

    foreach ($fields as $field => $value) {
        $component->set($field, $value);
    }

    $component->call('save')->assertHasErrors($errors);
})->with([
    'plain http' => [['name' => 'CRM', 'url' => 'http://crm.example.com/hook'], ['url' => 'starts_with']],
    'no events' => [['name' => 'CRM', 'url' => 'https://crm.example.com/hook', 'events' => []], ['events' => 'required']],
    'unknown event' => [['name' => 'CRM', 'url' => 'https://crm.example.com/hook', 'events' => ['ping']], ['events.0' => 'in']],
    'no name' => [['url' => 'https://crm.example.com/hook'], ['name' => 'required']],
]);

it('edits a webhook, keeps its secret and can replace it', function (): void {
    $webhook = Webhook::factory()->create(['name' => 'Old', 'url' => 'https://old.example.com/hook']);
    $secret = $webhook->secret;
    $this->actingAsAdmin();

    $component = Livewire::test('pages::admin.settings-webhooks')
        ->call('edit', $webhook->id)
        ->assertSet('name', 'Old')
        ->assertSee($secret)
        ->set('name', 'New')
        ->set('active', false)
        ->call('save')
        ->assertDispatched('toast-show', toastSays('Webhook saved.'));

    expect($webhook->refresh()->name)->toBe('New')
        ->and($webhook->active)->toBeFalse()
        ->and($webhook->secret)->toBe($secret);

    $component->call('edit', $webhook->id)->call('regenerateSecret');

    expect($webhook->refresh()->secret)->not->toBe($secret)->toStartWith('whsec_');
});

it('switches a webhook on and off, sends a test and shows recent deliveries', function (): void {
    $webhook = Webhook::factory()->create(['url' => 'https://hooks.example.com/inbox']);
    $this->actingAsAdmin();
    Http::fake(['hooks.example.com/*' => Http::sequence()->push('ok', 200)->push('nope', 500)]);

    $component = Livewire::test('pages::admin.settings-webhooks')->call('toggleActive', $webhook->id);

    expect($webhook->refresh()->active)->toBeFalse();

    $component->call('sendTest', $webhook->id)->assertDispatched('toast-show', toastSays('Test delivered.'));
    $component->call('sendTest', $webhook->id)->assertDispatched('toast-show', toastSays('The test didn’t go through: The endpoint answered with HTTP 500.'));

    $component->call('showDeliveries', $webhook->id)
        ->assertSee(['Delivered', 'Failed', 'Test', 'The endpoint answered with HTTP 500.', 'Send again']);
});

it('sends a failed delivery again', function (): void {
    Queue::fake();
    $delivery = WebhookDelivery::factory()->failed()->create();
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-webhooks')
        ->call('redeliver', $delivery->id)
        ->assertDispatched('toast-show', toastSays('Sent again.'));

    expect($delivery->refresh()->error)->toBeNull()->and($delivery->attempts)->toBe(0);
    Queue::assertPushed(DeliverWebhook::class);
});

it('deletes a webhook with its deliveries', function (): void {
    $delivery = WebhookDelivery::factory()->delivered()->create();
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-webhooks')
        ->call('confirmDelete', $delivery->webhook_id)
        ->call('delete')
        ->assertDispatched('toast-show', toastSays('Webhook deleted.'))
        ->assertSee('No webhooks yet.');

    expect(Webhook::query()->exists())->toBeFalse()->and(WebhookDelivery::query()->exists())->toBeFalse();
});

it('lets people who can only view the settings look but not change anything', function (): void {
    $webhook = Webhook::factory()->listeningTo([WebhookEvent::ORDER_PAID])->create(['last_status' => 404, 'last_error' => 'The endpoint answered with HTTP 404.']);
    $role = Role::factory()->create(['key' => 'viewer', 'abilities' => ['settings.view']]);
    $this->actingAs(User::factory()->role($role)->create(['active' => true]));

    Livewire::test('pages::admin.settings-webhooks')
        ->assertSee(['Order paid', 'Failed (404)'])
        ->assertDontSee('Add webhook')
        ->call('showDeliveries', $webhook->id)
        ->assertSee('Nothing sent yet.')
        ->call('add')
        ->assertForbidden();
});
