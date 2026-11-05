<?php

declare(strict_types=1);

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Enums\WebhookEvent;
use App\Jobs\SubscribeToNewsletter;
use App\Models\Block;
use App\Models\Page;
use App\Models\Settings;
use App\Models\Submission;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\NewsletterResult;
use App\Services\NewsletterService;
use App\Services\Turnstile;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function mailchimpKey(): string
{
    return str_repeat('ab12', 8).'-us21';
}

/**
 * @param  array<string, mixed>  $settings
 */
function connectNewsletter(string $provider, array $settings = []): void
{
    Settings::set([
        'newsletter_provider' => $provider,
        'newsletter_api_key' => $provider === 'mailchimp' ? mailchimpKey() : $provider.'-key-NL01',
        'newsletter_list_id' => $provider === 'kit' ? '311' : 'aud123',
        ...$settings,
    ]);
}

/**
 * @param  array<string, mixed>  $content
 */
function newsletterSignup(array $content = []): Testable
{
    $page = Page::factory()->create();
    $block = Block::factory()->create([
        'blockable_id' => $page->id,
        'blockable_type' => 'page',
        'type' => BlockType::NEWSLETTER,
        'content' => [...BlockType::NEWSLETTER->defaultContent(), ...$content],
    ]);

    return Livewire::test('site.newsletter-signup', ['config' => $block->content, 'blockId' => $block->id, 'pageId' => $page->id]);
}

function newsletterSubmission(string $status = 'pending'): Submission
{
    return Submission::factory()->create([
        'type' => 'newsletter',
        'name' => 'Ada',
        'email' => 'Ada@Example.com',
        'ip' => '203.0.113.9',
        'metadata' => ['newsletter' => ['provider' => 'mailchimp', 'status' => $status, 'error' => null, 'at' => now()->toIso8601String()]],
    ]);
}

it('adds a Mailchimp member in the data centre named by the key, pending confirmation by default', function (): void {
    connectNewsletter('mailchimp');
    Http::fake(['us21.api.mailchimp.com/*' => Http::response(['status' => 'pending'])]);

    $result = resolve(NewsletterService::class)->subscribe('Ada@Example.com', 'Ada', '203.0.113.9');

    expect($result->synced)->toBeTrue();
    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && $request->url() === 'https://us21.api.mailchimp.com/3.0/lists/aud123/members/'.md5('ada@example.com')
        && $request->header('Authorization')[0] === 'Basic '.base64_encode('wireup:'.mailchimpKey())
        && $request->data() === ['email_address' => 'Ada@Example.com', 'status_if_new' => 'pending', 'merge_fields' => ['FNAME' => 'Ada'], 'ip_signup' => '203.0.113.9']);
});

it('subscribes Mailchimp members straight away when confirmation is off', function (): void {
    connectNewsletter('mailchimp', ['newsletter_double_opt_in' => false]);
    Http::fake(['us21.api.mailchimp.com/*' => Http::response(['status' => 'subscribed'])]);

    resolve(NewsletterService::class)->subscribe('ada@example.com');

    Http::assertSent(fn (Request $request): bool => $request->data() === ['email_address' => 'ada@example.com', 'status_if_new' => 'subscribed']);
});

it('creates the Kit subscriber, then adds them to the form', function (): void {
    connectNewsletter('kit');
    Http::fake(['api.kit.com/*' => Http::response(['subscriber' => ['id' => 1]], 201)]);

    expect(resolve(NewsletterService::class)->subscribe('ada@example.com', 'Ada')->synced)->toBeTrue();

    Http::assertSentInOrder([
        fn (Request $request): bool => $request->url() === 'https://api.kit.com/v4/subscribers'
            && $request->header('X-Kit-Api-Key')[0] === 'kit-key-NL01'
            && $request->data() === ['email_address' => 'ada@example.com', 'first_name' => 'Ada'],
        fn (Request $request): bool => $request->url() === 'https://api.kit.com/v4/forms/311/subscribers'
            && $request->data() === ['email_address' => 'ada@example.com'],
    ]);
});

it('stops at the first Kit error and reports it', function (): void {
    connectNewsletter('kit');
    Http::fake(['api.kit.com/*' => Http::response(['errors' => ['Email address is invalid']], 422)]);

    $result = resolve(NewsletterService::class)->subscribe('ada@example.com');

    expect($result->synced)->toBeFalse()
        ->and($result->retryable)->toBeFalse()
        ->and($result->message)->toBe('Email address is invalid');
    Http::assertSentCount(1);
});

it('adds Buttondown subscribers and treats an existing one as done', function (): void {
    connectNewsletter('buttondown', ['newsletter_double_opt_in' => false]);
    Http::fake(['api.buttondown.com/*' => Http::sequence()
        ->push(['id' => 'sub_1'], 201)
        ->push(['code' => 'email_already_exists', 'detail' => 'That email is already subscribed.'], 400)]);

    $service = resolve(NewsletterService::class);

    expect($service->subscribe('ada@example.com', 'Ada', '203.0.113.9')->synced)->toBeTrue()
        ->and($service->subscribe('ada@example.com')->synced)->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.buttondown.com/v1/subscribers'
        && $request->header('Authorization')[0] === 'Token buttondown-key-NL01'
        && $request->data() === ['email_address' => 'ada@example.com', 'type' => 'regular', 'metadata' => ['first_name' => 'Ada'], 'ip_address' => '203.0.113.9']);
});

it('marks outages and rate limits as worth retrying, and refusals as final', function (): void {
    connectNewsletter('buttondown');
    Http::fake(['api.buttondown.com/*' => Http::sequence()
        ->push('down', 503)
        ->push('slow down', 429)
        ->pushFailedConnection('timed out')
        ->push(['detail' => 'Invalid token.'], 401)
        ->push('not found', 404)]);

    $service = resolve(NewsletterService::class);
    $results = array_map(fn (): mixed => $service->subscribe('ada@example.com'), range(1, 5));

    expect(array_map(fn (NewsletterResult $result): bool => $result->retryable, $results))->toBe([true, true, true, false, false])
        ->and($results[2]->message)->toBe('timed out')
        ->and($results[3]->message)->toBe('Invalid token.')
        ->and($results[4]->message)->toBe('The service answered with HTTP 404.');

    Http::assertSent(fn (Request $request): bool => ! array_key_exists('type', $request->data()));
});

it('only counts as connected with a known service and a key', function (): void {
    Http::fake();
    $service = resolve(NewsletterService::class);

    expect($service->providerName())->toBeNull()
        ->and($service->subscribe('ada@example.com')->message)->toBe('No newsletter service is connected.');

    Settings::set(['newsletter_provider' => 'mailchimp']);
    expect($service->provider())->toBeNull();

    Settings::set(['newsletter_provider' => 'unknown', 'newsletter_api_key' => 'key']);
    expect($service->provider())->toBeNull();

    connectNewsletter('kit');
    expect($service->providerName())->toBe('Kit');
    Http::assertNothingSent();
});

it('records a successful sync on the submission and does not sync it twice', function (): void {
    connectNewsletter('mailchimp');
    Http::fake(['us21.api.mailchimp.com/*' => Http::response([])]);
    $submission = newsletterSubmission();

    new SubscribeToNewsletter($submission->id)->withFakeQueueInteractions()->handle(resolve(NewsletterService::class));

    expect($submission->refresh()->metadata['newsletter'])->toMatchArray(['provider' => 'mailchimp', 'status' => 'synced', 'error' => null]);

    new SubscribeToNewsletter($submission->id)->withFakeQueueInteractions()->handle(resolve(NewsletterService::class));
    new SubscribeToNewsletter(999999)->withFakeQueueInteractions()->handle(resolve(NewsletterService::class));

    Http::assertSentCount(1);
});

it('retries an outage with growing pauses, then records the failure', function (): void {
    connectNewsletter('mailchimp');
    Http::fake(['us21.api.mailchimp.com/*' => Http::response(['detail' => 'Service unavailable'], 503)]);
    $submission = newsletterSubmission();
    $service = resolve(NewsletterService::class);

    $first = new SubscribeToNewsletter($submission->id)->withFakeQueueInteractions();
    $first->handle($service);
    $first->assertReleased(60);

    expect($submission->refresh()->metadata['newsletter'])->toMatchArray(['status' => 'retrying', 'error' => 'Service unavailable']);

    $second = new SubscribeToNewsletter($submission->id)->withFakeQueueInteractions();
    $second->job->attempts = 2;
    $second->handle($service);
    $second->assertReleased(300);

    $last = new SubscribeToNewsletter($submission->id)->withFakeQueueInteractions();
    $last->job->attempts = 5;
    $last->handle($service);
    $last->assertFailed();
    $last->failed(new RuntimeException('Service unavailable'));

    expect($submission->refresh()->metadata['newsletter'])->toMatchArray(['status' => 'failed', 'error' => 'Service unavailable']);
});

it('gives up at once when the service refuses the sign-up', function (): void {
    connectNewsletter('mailchimp');
    Http::fake(['us21.api.mailchimp.com/*' => Http::response(['detail' => 'ada@example.com is in a compliance state.'], 400)]);
    $submission = newsletterSubmission();

    $job = new SubscribeToNewsletter($submission->id)->withFakeQueueInteractions();
    $job->handle(resolve(NewsletterService::class));

    $job->assertFailed();
    expect($submission->refresh()->metadata['newsletter'])->toMatchArray(['status' => 'failed', 'error' => 'ada@example.com is in a compliance state.']);
});

it('records a job that dies mid-retry as failed', function (): void {
    $retrying = newsletterSubmission('retrying');
    $plain = Submission::factory()->create(['metadata' => null]);

    new SubscribeToNewsletter($retrying->id)->failed(new RuntimeException('Timed out'));
    new SubscribeToNewsletter($plain->id)->failed(null);
    new SubscribeToNewsletter(999999)->failed(null);

    expect($retrying->refresh()->metadata['newsletter'])->toMatchArray(['provider' => 'mailchimp', 'status' => 'failed', 'error' => 'Timed out'])
        ->and($plain->refresh()->metadata)->toBeNull();
});

it('keeps each sign-up in the Inbox, adds it to the list and tells webhooks', function (): void {
    connectNewsletter('mailchimp');
    config()->set('services.slack.webhook_url', 'https://hooks.slack.com/services/T0/B0/xyz');
    Notification::fake();
    Http::fake(['us21.api.mailchimp.com/*' => Http::response([]), 'hooks.example.com/*' => Http::response()]);
    Webhook::factory()->create(['url' => 'https://hooks.example.com/in', 'events' => [WebhookEvent::NEWSLETTER_SUBSCRIBED->value]]);

    $component = newsletterSignup(['askName' => true])
        ->set('name', ' Ada ')
        ->set('email', ' ada@example.com ');

    $this->travel(5)->seconds();

    $component->call('subscribe')
        ->assertHasNoErrors()
        ->assertSet('subscribed', true)
        ->assertSet('email', '')
        ->assertSee('Thanks for signing up!');

    $submission = Submission::query()->sole();

    expect($submission->type)->toBe('newsletter')
        ->and($submission->form_name)->toBe('Newsletter sign-up')
        ->and($submission->name)->toBe('Ada')
        ->and($submission->email)->toBe('ada@example.com')
        ->and($submission->metadata['newsletter']['status'])->toBe('synced')
        ->and(WebhookDelivery::query()->sole()->event)->toBe(WebhookEvent::NEWSLETTER_SUBSCRIBED->value);

    Notification::assertNothingSent();
});

it('keeps sign-ups in the Inbox without calling anyone when no service is connected', function (): void {
    Http::fake();
    Queue::fake();

    $component = newsletterSignup()->set('name', 'Ada')->set('email', 'ada@example.com');

    $this->travel(5)->seconds();

    $component->call('subscribe')->assertHasNoErrors()->assertSet('subscribed', true);

    expect(Submission::query()->sole())
        ->name->toBeNull()
        ->metadata->toBeNull();
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

it('asks for a valid email address', function (string $email): void {
    $component = newsletterSignup()->set('email', $email);

    $this->travel(5)->seconds();

    $component->call('subscribe')->assertHasErrors(['email'])->assertSet('subscribed', false);

    expect(Submission::query()->count())->toBe(0);
})->with(['missing' => '', 'malformed' => 'not-an-email']);

it('turns away bots that fill the hidden field or submit too fast', function (): void {
    $filled = newsletterSignup()->set('email', 'bot@example.com')->set('website', 'spam');
    $this->travel(5)->seconds();
    $filled->call('subscribe')->assertHasErrors(['form']);

    newsletterSignup()->set('email', 'bot@example.com')->call('subscribe')->assertHasErrors(['form']);

    expect(Submission::query()->count())->toBe(0);
});

it('slows down repeated sign-ups from one address', function (): void {
    $component = newsletterSignup();

    $this->travel(5)->seconds();

    foreach (range(1, 5) as $attempt) {
        $component->set('email', "person{$attempt}@example.com")->set('subscribed', false)->call('subscribe')->assertHasNoErrors();
    }

    $component->set('email', 'one-more@example.com')->call('subscribe')->assertHasErrors(['form']);

    expect(Submission::query()->count())->toBe(5);
});

it('refuses a sign-up that Turnstile rejects', function (): void {
    Settings::set(['turnstile_site_key' => '1x00000000000000000000AA', 'turnstile_secret_key' => 'turnstile-secret']);
    Http::fake([Turnstile::VERIFY_URL => Http::response(['success' => false])]);

    $component = newsletterSignup()->assertSeeHtml('data-turnstile')->set('email', 'ada@example.com')->set('turnstileToken', 'bad');

    $this->travel(5)->seconds();

    $component->call('subscribe')->assertHasErrors(['form']);

    expect(Submission::query()->count())->toBe(0);
});

it('renders the block copy and falls back to friendly defaults', function (): void {
    newsletterSignup([
        'heading' => ['en' => '<p>Join the list</p>'],
        'intro' => ['en' => '<p>One email a month.</p>'],
        'askName' => true,
        'namePlaceholder' => ['en' => 'Your first name'],
        'emailPlaceholder' => ['en' => 'you@example.com'],
        'buttonText' => ['en' => 'Count me in'],
        'privacyNote' => ['en' => '<p>Unsubscribe any time.</p>'],
        'layout' => 'stacked',
        'align' => 'left',
    ])
        ->assertSee('Join the list')
        ->assertSee('One email a month.')
        ->assertSeeHtml('placeholder="Your first name"')
        ->assertSeeHtml('placeholder="you@example.com"')
        ->assertSee('Count me in')
        ->assertSee('Unsubscribe any time.')
        ->assertSeeHtml('max-w-md')
        ->assertDontSeeHtml('text-center');

    newsletterSignup()
        ->assertSeeHtml('placeholder="Your email"')
        ->assertDontSeeHtml('placeholder="First name"')
        ->assertSee('Subscribe')
        ->assertSeeHtml('mx-auto text-center')
        ->assertSeeHtml('sm:flex-row');
});

it('shows the newsletter sign-up block on the page', function (): void {
    $page = Page::factory()->create(['title' => 'Join', 'status' => ContentStatus::PUBLISHED, 'published_at' => now()->subDay()]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'join']);
    $page->updateBlocks([['id' => 'new-1', 'type' => 'newsletter', 'content' => ['heading' => ['en' => '<p>Join the list</p>']]]]);

    $this->get('/join')->assertOk()->assertSee('Join the list')->assertSeeLivewire('site.newsletter-signup');
});

it('shows whether a sign-up reached the newsletter service in the Inbox', function (string $status, ?string $error, string $label): void {
    $submission = newsletterSubmission($status);
    $submission->update(['metadata' => ['newsletter' => [...$submission->metadata['newsletter'], 'error' => $error]]]);

    $this->actingAsAdmin();

    $page = Livewire::test('pages::admin.inbox-show', ['submission' => $submission])->assertSee($label);

    if ($error !== null) {
        $page->assertSee($error);
    }
})->with([
    'synced' => ['synced', null, 'Added to Mailchimp'],
    'failed' => ['failed', 'Invalid API key', 'Not added to Mailchimp'],
    'waiting' => ['retrying', 'Service unavailable', 'Waiting to be added to Mailchimp'],
]);

it('shows no newsletter status on other messages', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.inbox-show', ['submission' => Submission::factory()->create(['metadata' => null])])
        ->assertDontSeeHtml('data-newsletter-status');
});

it('connects, tests and disconnects Mailchimp from the integrations screen', function (): void {
    $this->actingAsAdmin();

    $component = Livewire::test('pages::admin.settings-integrations')
        ->set('newsletterForm.newsletter_provider', 'mailchimp')
        ->set('newsletterForm.newsletter_api_key', ' '.mailchimpKey().' ')
        ->set('newsletterForm.newsletter_list_id', ' aud123 ')
        ->set('newsletterForm.newsletter_double_opt_in', false)
        ->call('connectNewsletter')
        ->assertHasNoErrors()
        ->assertSet('newsletterForm.newsletter_api_key', '')
        ->assertDispatched('toast-show', toastSays('Mailchimp connected.'));

    expect(Settings::get('newsletter_provider'))->toBe('mailchimp')
        ->and(Settings::get('newsletter_api_key'))->toBe(mailchimpKey())
        ->and(Settings::get('newsletter_list_id'))->toBe('aud123')
        ->and(Settings::get('newsletter_double_opt_in'))->toBeFalse();

    $html = $this->get(route('admin.settings-integrations'))->getContent();

    expect($html)->not->toContain(str_repeat('ab12', 4))->and($html)->toContain('…us21');

    Http::fake(['us21.api.mailchimp.com/*' => Http::sequence()
        ->push(['name' => 'Readers'])
        ->push(['detail' => 'Your API key may be invalid.'], 401)
        ->pushFailedConnection('offline')]);

    $component->call('testNewsletter')->assertDispatched('toast-show', toastSays('Connected to Readers on Mailchimp.'));
    $component->call('testNewsletter')->assertDispatched('toast-show', toastSays('Mailchimp said: Your API key may be invalid.'));
    $component->call('testNewsletter')->assertDispatched('toast-show', toastSays('Could not reach Mailchimp: offline'));

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://us21.api.mailchimp.com/3.0/lists/aud123?fields=name');

    $component->call('disconnect', 'newsletter');

    expect(Settings::get('newsletter_api_key'))->toBe('')
        ->and(resolve(NewsletterService::class)->provider())->toBeNull();
});

it('checks that the Kit form exists when testing', function (): void {
    $this->actingAsAdmin();
    Http::fake(['api.kit.com/v4/forms*' => Http::response(['forms' => [['id' => 311, 'name' => 'Footer form']]])]);

    $component = Livewire::test('pages::admin.settings-integrations')
        ->set('newsletterForm.newsletter_provider', 'kit')
        ->set('newsletterForm.newsletter_api_key', 'kit_live_key')
        ->set('newsletterForm.newsletter_list_id', '311')
        ->call('testNewsletter')
        ->assertDispatched('toast-show', toastSays('Connected to Footer form on Kit.'));

    $component->set('newsletterForm.newsletter_list_id', '999')
        ->call('testNewsletter')
        ->assertDispatched('toast-show', toastSays('Kit has no form with the ID 999.'));
});

it('connects Buttondown without a list ID', function (): void {
    $this->actingAsAdmin();
    Http::fake(['api.buttondown.com/v1/newsletters' => Http::response(['results' => [['name' => 'Weekly notes']]])]);

    Livewire::test('pages::admin.settings-integrations')
        ->set('newsletterForm.newsletter_provider', 'buttondown')
        ->set('newsletterForm.newsletter_api_key', 'bd-key')
        ->set('newsletterForm.newsletter_list_id', 'leftover')
        ->call('testNewsletter')
        ->assertDispatched('toast-show', toastSays('Connected to Weekly notes on Buttondown.'))
        ->call('connectNewsletter')
        ->assertHasNoErrors();

    expect(Settings::get('newsletter_list_id'))->toBe('')
        ->and(resolve(NewsletterService::class)->providerName())->toBe('Buttondown');
});

it('checks the newsletter key and list ID before saving', function (): void {
    connectNewsletter('mailchimp');
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->set('newsletterForm.newsletter_api_key', 'abcdef0123456789abcdef0123456789')
        ->call('connectNewsletter')
        ->assertHasErrors(['newsletterForm.newsletter_api_key' => 'regex'])
        ->set('newsletterForm.newsletter_provider', 'kit')
        ->set('newsletterForm.newsletter_api_key', '')
        ->set('newsletterForm.newsletter_list_id', 'not an id')
        ->call('connectNewsletter')
        ->assertHasErrors(['newsletterForm.newsletter_api_key' => 'required', 'newsletterForm.newsletter_list_id' => 'regex'])
        ->set('newsletterForm.newsletter_api_key', 'has spaces in it')
        ->call('connectNewsletter')
        ->assertHasErrors(['newsletterForm.newsletter_api_key' => 'regex']);

    expect(Settings::get('newsletter_provider'))->toBe('mailchimp');
});

it('explains where to find the key and list for each service', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.settings-integrations')
        ->assertSee('In Mailchimp under Profile → Extras → API keys.')
        ->assertSee('Audience ID')
        ->assertSee('Ask people to confirm their email')
        ->set('newsletterForm.newsletter_provider', 'kit')
        ->assertSee('A V4 key, in Kit under Settings → Developer.')
        ->assertSee('The number in the form’s address in Kit.')
        ->assertDontSee('Ask people to confirm their email')
        ->set('newsletterForm.newsletter_provider', 'buttondown')
        ->assertSee('In Buttondown under Settings → API.')
        ->assertDontSee('Audience ID');
});

it('edits the newsletter block and says when no service is connected', function (): void {
    $this->actingAsAdmin();

    $component = Livewire::test('pages::admin.pages-edit', ['page' => Page::factory()->create()])
        ->set('blocks', ['b1' => ['id' => 'b1', 'type' => 'newsletter', 'position' => 0, 'content' => BlockType::NEWSLETTER->defaultContent()]])
        ->assertSee('Connect Mailchimp, Kit or Buttondown')
        ->assertDontSee('First name placeholder')
        ->set('blocks.b1.content.askName', true)
        ->assertSee('First name placeholder');

    connectNewsletter('kit');

    $component->set('blocks.b1.content.layout', 'stacked')->assertDontSee('Connect Mailchimp, Kit or Buttondown');
});
