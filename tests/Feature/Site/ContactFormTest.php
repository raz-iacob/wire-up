<?php

declare(strict_types=1);

use App\Enums\BlockType;
use App\Models\Block;
use App\Models\Page;
use App\Models\Settings;
use App\Models\Submission;
use App\Notifications\SubmissionReceived;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * @param  array<string, mixed>  $overrides
 */
function contactBlock(array $overrides = []): Block
{
    $page = Page::factory()->create();

    return Block::factory()->create([
        'blockable_id' => $page->id,
        'blockable_type' => 'page',
        'type' => BlockType::CONTACT_FORM,
        'content' => array_replace_recursive(BlockType::CONTACT_FORM->defaultContent(), $overrides),
    ]);
}

function contactForm(Block $block): Testable
{
    $config = $block->content ?? [];
    unset($config['recipient']);

    return Livewire::test('site.contact-form', [
        'config' => $config,
        'blockId' => $block->id,
        'pageId' => $block->blockable_id,
    ]);
}

it('validates the enabled required fields', function (): void {
    $component = contactForm(contactBlock());

    test()->travel(5)->seconds();

    $component->call('submit')
        ->assertHasErrors(['name', 'email', 'message'])
        ->assertSet('sent', false);

    expect(Submission::query()->count())->toBe(0);
});

it('rejects an invalid email address', function (): void {
    $component = contactForm(contactBlock());

    test()->travel(5)->seconds();

    $component->set('name', 'Ada')
        ->set('email', 'not-an-email')
        ->set('message', 'Hello')
        ->call('submit')
        ->assertHasErrors(['email']);
});

it('persists and emails a successful submission', function (): void {
    Notification::fake();
    $block = contactBlock(['recipient' => 'owner@example.com', 'formName' => 'Massage enquiry']);

    $component = contactForm($block)
        ->set('name', 'Ada Lovelace')
        ->set('email', 'ada@example.com')
        ->set('message', 'Hello there');

    test()->travel(5)->seconds();

    $component->call('submit')
        ->assertHasNoErrors()
        ->assertSet('sent', true)
        ->assertSet('name', '');

    $this->assertDatabaseHas('submissions', [
        'type' => 'contact',
        'form_name' => 'Massage enquiry',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'message' => 'Hello there',
        'page_id' => $block->blockable_id,
        'block_id' => $block->id,
        'locale' => 'en',
    ]);

    Notification::assertSentOnDemand(
        SubmissionReceived::class,
        fn (SubmissionReceived $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === ['owner@example.com'],
    );
});

it('posts the submission to the slack webhook when configured', function (): void {
    config()->set('services.slack.webhook_url', 'https://hooks.slack.com/services/T0/B0/xyz');
    Http::fake(['hooks.slack.com/*' => Http::response('ok')]);

    $component = contactForm(contactBlock(['formName' => 'Massage enquiry']))
        ->set('name', 'Ada Lovelace')
        ->set('email', 'ada@example.com')
        ->set('message', 'Hello there');

    test()->travel(5)->seconds();

    $component->call('submit')
        ->assertHasNoErrors()
        ->assertSet('sent', true);

    Http::assertSent(fn ($request): bool => (string) $request->url() === 'https://hooks.slack.com/services/T0/B0/xyz'
        && $request['text'] === 'New submission from Massage enquiry'
        && str_contains(json_encode($request['blocks']) ?: '', 'Ada Lovelace'));
});

it('routes to multiple block recipients', function (): void {
    Notification::fake();
    $block = contactBlock(['recipient' => 'one@example.com, two@example.com']);

    $component = contactForm($block)
        ->set('name', 'Ada')
        ->set('email', 'ada@example.com')
        ->set('message', 'Hi');

    test()->travel(5)->seconds();

    $component->call('submit')->assertHasNoErrors();

    Notification::assertSentOnDemand(
        SubmissionReceived::class,
        fn (SubmissionReceived $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === ['one@example.com', 'two@example.com'],
    );
});

it('falls back to the site contact email when the block has no recipient', function (): void {
    Notification::fake();
    Settings::set(['contact_email' => 'site@example.com']);
    $block = contactBlock(['recipient' => '']);

    $component = contactForm($block)
        ->set('name', 'Ada')
        ->set('email', 'ada@example.com')
        ->set('message', 'Hi');

    test()->travel(5)->seconds();

    $component->call('submit')->assertHasNoErrors();

    Notification::assertSentOnDemand(
        SubmissionReceived::class,
        fn (SubmissionReceived $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === ['site@example.com'],
    );
});

it('falls back to the mail from address when nothing else is set', function (): void {
    Notification::fake();
    config()->set('site.contact_email');
    config()->set('mail.from.address', 'from@example.com');
    $block = contactBlock(['recipient' => '']);

    $component = contactForm($block)
        ->set('name', 'Ada')
        ->set('email', 'ada@example.com')
        ->set('message', 'Hi');

    test()->travel(5)->seconds();

    $component->call('submit')->assertHasNoErrors();

    Notification::assertSentOnDemand(
        SubmissionReceived::class,
        fn (SubmissionReceived $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === ['from@example.com'],
    );
});

it('stores the submission but sends nothing when no recipient can be resolved', function (): void {
    Notification::fake();
    config()->set('site.contact_email');
    config()->set('mail.from.address', '');
    $block = contactBlock(['recipient' => '']);

    $component = contactForm($block)
        ->set('name', 'Ada')
        ->set('email', 'ada@example.com')
        ->set('message', 'Hi');

    test()->travel(5)->seconds();

    $component->call('submit')->assertHasNoErrors();

    expect(Submission::query()->count())->toBe(1);
    Notification::assertNothingSent();
});

it('drops submissions that fill the honeypot without showing success', function (): void {
    Notification::fake();
    $component = contactForm(contactBlock(['recipient' => 'owner@example.com']))
        ->set('name', 'Bot')
        ->set('email', 'bot@example.com')
        ->set('message', 'spam')
        ->set('website', 'http://spam.test');

    test()->travel(5)->seconds();

    $component->call('submit')
        ->assertHasErrors('form')
        ->assertSet('sent', false);

    expect(Submission::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('drops submissions made too quickly without showing success', function (): void {
    Notification::fake();
    $component = contactForm(contactBlock(['recipient' => 'owner@example.com']))
        ->set('name', 'Bot')
        ->set('email', 'bot@example.com')
        ->set('message', 'spam');

    $component->call('submit')
        ->assertHasErrors('form')
        ->assertSet('sent', false);

    expect(Submission::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('throttles repeated submissions from the same visitor', function (): void {
    $component = contactForm(contactBlock());

    test()->travel(5)->seconds();

    foreach (range(1, 5) as $ignored) {
        $component->call('submit');
    }

    $component->call('submit')->assertHasErrors('form');
});

it('validates custom fields of each type', function (): void {
    $block = contactBlock([
        'fieldOrder' => ['colour', 'age', 'terms'],
        'customFields' => [
            ['id' => 'colour', 'label' => ['en' => 'Colour'], 'type' => 'select', 'required' => true, 'options' => "Red\nBlue"],
            ['id' => 'age', 'label' => ['en' => 'Age'], 'type' => 'number', 'required' => false, 'options' => ''],
            ['id' => 'terms', 'label' => ['en' => 'Accept terms'], 'type' => 'checkbox', 'required' => true, 'options' => ''],
        ],
    ]);

    $component = contactForm($block)
        ->set('custom.colour', 'Green')
        ->set('custom.age', 'not-a-number')
        ->set('custom.terms', false);

    test()->travel(5)->seconds();

    $component->call('submit')
        ->assertHasErrors(['custom.colour', 'custom.age', 'custom.terms']);
});

it('persists custom field values into metadata', function (): void {
    Notification::fake();
    $block = contactBlock([
        'recipient' => 'owner@example.com',
        'customFields' => [
            ['id' => 'colour', 'label' => ['en' => 'Colour'], 'type' => 'select', 'required' => true, 'options' => "Red\nBlue"],
            ['id' => 'terms', 'label' => ['en' => 'Accept terms'], 'type' => 'checkbox', 'required' => true, 'options' => ''],
        ],
    ]);

    $component = contactForm($block)
        ->set('name', 'Ada')
        ->set('email', 'ada@example.com')
        ->set('message', 'Hi')
        ->set('custom.colour', 'Blue')
        ->set('custom.terms', true);

    test()->travel(5)->seconds();

    $component->call('submit')->assertHasNoErrors();

    $submission = Submission::query()->latest('id')->firstOrFail();

    expect($submission->metadata)->toBe([
        'colour' => ['label' => 'Colour', 'value' => 'Blue'],
        'terms' => ['label' => 'Accept terms', 'value' => true],
    ]);
});
