<?php

declare(strict_types=1);

use App\Ai\Agents\SiteAssistant;
use App\Enums\ContentStatus;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Responses\Data\ToolCall;
use Livewire\Livewire;

/**
 * @param  array<int, string>  $toolNames
 */
function seedAssistantMessage(string $conversationId, int $userId, string $role, string $content, array $toolNames = []): void
{
    DB::table('agent_conversation_messages')->insert([
        'id' => (string) Str::uuid7(),
        'conversation_id' => $conversationId,
        'user_id' => $userId,
        'agent' => SiteAssistant::class,
        'role' => $role,
        'content' => $content,
        'attachments' => '[]',
        'tool_calls' => json_encode(array_map(fn (string $name): array => ['name' => $name], $toolNames)),
        'tool_results' => '[]',
        'usage' => '[]',
        'meta' => '[]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('rate limits rapid messages', function (): void {
    $this->actingAsAdmin();

    SiteAssistant::fake(['Hi.']);

    foreach (range(1, 20) as $ignored) {
        RateLimiter::hit('assistant:'.auth()->id(), 60);
    }

    Livewire::test('admin.assistant')
        ->call('send', 'Hello there')
        ->assertSet('messages.0.error', true)
        ->assertSee('too quickly');

    SiteAssistant::assertNeverPrompted();
});

it('shows the empty state before any messages', function (): void {
    $this->actingAsAdmin();

    Livewire::test('admin.assistant')
        ->assertSet('messages', [])
        ->assertSet('conversationId', null)
        ->assertSee('Ask me to build');
});

it('sends a prompt and appends the exchange', function (): void {
    $this->actingAsAdmin();
    config()->set('site.ai_model', 'claude-opus-4-8');

    SiteAssistant::fake(['Here is your About page.']);

    Livewire::test('admin.assistant')
        ->call('send', 'Build an About page')
        ->assertSet('messages.0.role', 'user')
        ->assertSet('messages.0.content', 'Build an About page')
        ->assertSet('messages.1.role', 'assistant')
        ->assertSet('messages.1.content', 'Here is your About page.')
        ->assertSet('messages.1.animate', true)
        ->assertSee('Here is your About page.');

    SiteAssistant::assertPrompted('Build an About page');
});

it('prompts without a model override when none is configured', function (): void {
    $this->actingAsAdmin();
    config()->set('site.ai_model', '');

    SiteAssistant::fake(['Done.']);

    Livewire::test('admin.assistant')
        ->call('send', 'Change the theme')
        ->assertSet('messages.1.content', 'Done.');

    SiteAssistant::assertPrompted('Change the theme');
});

it('records tool activity as the assistant works', function (): void {
    $this->actingAsAdmin();
    config()->set('site.ai_model', '');

    SiteAssistant::fake([
        new ToolCall('call-1', 'get-settings', []),
        'I checked your settings — all good.',
    ]);

    Livewire::test('admin.assistant')
        ->call('send', 'What theme am I using?')
        ->assertSet('messages.1.role', 'assistant')
        ->assertSet('messages.1.content', 'I checked your settings — all good.')
        ->assertSet('messages.1.tools.0.name', 'get-settings')
        ->assertSet('messages.1.tools.0.status', 'done');
});

it('renders tool chips for every activity state', function (): void {
    $this->actingAsAdmin();

    $html = Livewire::test('admin.assistant')->instance()->toolChipsHtml([
        ['name' => 'create-page', 'status' => 'running'],
        ['name' => 'publish-page', 'status' => 'done'],
        ['name' => 'update-design', 'status' => 'failed'],
        ['name' => 'mystery-tool', 'status' => 'running'],
    ]);

    expect($html)
        ->toContain('Creating page')
        ->toContain('animate-pulse')
        ->toContain('Published')
        ->toContain('&check;')
        ->toContain('Updating design')
        ->toContain('&times;')
        ->toContain('mystery-tool');
});

it('asks for confirmation before publishing and does not publish yet', function (): void {
    $this->actingAsAdmin();
    $page = Page::factory()->create(['title' => 'Launch Me', 'status' => ContentStatus::DRAFT]);

    SiteAssistant::fake([
        new ToolCall('c1', 'publish-page', ['page' => $page->id]),
        'The page is ready — confirm below to publish it.',
    ]);

    Livewire::test('admin.assistant')
        ->call('send', 'Publish the Launch Me page')
        ->assertSet('messages.1.pending.0.name', 'publish-page')
        ->assertSet('messages.1.pending.0.status', 'awaiting')
        ->assertSee('Publish “Launch Me”?');

    expect($page->refresh()->status)->toBe(ContentStatus::DRAFT);
});

it('publishes only after the owner confirms', function (): void {
    $this->actingAsAdmin();
    $page = Page::factory()->create(['status' => ContentStatus::DRAFT]);

    SiteAssistant::fake([new ToolCall('c1', 'publish-page', ['page' => $page->id]), 'Ready to publish.']);

    Livewire::test('admin.assistant')
        ->call('send', 'Publish it')
        ->call('confirmAction', 1, 0)
        ->assertSet('messages.1.pending.0.status', 'confirmed');

    expect($page->refresh()->status)->toBe(ContentStatus::PUBLISHED);
});

it('leaves the page unpublished when the owner cancels', function (): void {
    $this->actingAsAdmin();
    $page = Page::factory()->create(['status' => ContentStatus::DRAFT]);

    SiteAssistant::fake([new ToolCall('c1', 'publish-page', ['page' => $page->id]), 'Ready to publish.']);

    Livewire::test('admin.assistant')
        ->call('send', 'Publish it')
        ->call('rejectAction', 1, 0)
        ->assertSet('messages.1.pending.0.status', 'rejected');

    expect($page->refresh()->status)->toBe(ContentStatus::DRAFT);
});

it('shows failures inline in the chat', function (): void {
    $this->actingAsAdmin();

    SiteAssistant::fake(fn (): never => throw new RuntimeException('provider down'));

    Livewire::test('admin.assistant')
        ->call('send', 'Build a page')
        ->assertSet('messages.0.role', 'user')
        ->assertSet('messages.1.role', 'assistant')
        ->assertSet('messages.1.error', true)
        ->assertSee('I ran into a problem');
});

it('ignores an empty prompt without erroring', function (): void {
    $this->actingAsAdmin();

    SiteAssistant::fake();

    Livewire::test('admin.assistant')
        ->call('send', '   ')
        ->assertHasNoErrors()
        ->assertSet('messages', []);

    SiteAssistant::assertNeverPrompted();
});

it('loads prior conversation history on mount', function (): void {
    $user = User::factory()->create(['role' => 'owner']);
    $this->actingAs($user);

    $conversationId = resolve(ConversationStore::class)->storeConversation($user->id, 'Earlier chat');
    seedAssistantMessage($conversationId, $user->id, 'user', 'Make a hero section');
    seedAssistantMessage($conversationId, $user->id, 'assistant', 'Added a **hero** to the home page.');

    Livewire::test('admin.assistant')
        ->assertSet('conversationId', $conversationId)
        ->assertSet('messages.0.content', 'Make a hero section')
        ->assertSet('messages.1.content', 'Added a **hero** to the home page.')
        ->assertSeeHtml('<strong>hero</strong>');
});

it('rebuilds tool activity chips when reloading a past chat', function (): void {
    $user = User::factory()->create(['role' => 'owner']);
    $this->actingAs($user);

    $conversationId = resolve(ConversationStore::class)->storeConversation($user->id, 'Earlier chat');
    seedAssistantMessage($conversationId, $user->id, 'user', 'Build an About page');
    seedAssistantMessage($conversationId, $user->id, 'assistant', 'Done — added the page.', ['create-page', 'update-page-blocks', 'publish-page']);

    Livewire::test('admin.assistant')
        ->assertSet('messages.1.tools.0.name', 'create-page')
        ->assertSet('messages.1.tools.1.name', 'update-page-blocks')
        ->assertSee('Created page')
        ->assertSee('Updated page')
        ->assertDontSee('Published');
});

it('starts a new conversation', function (): void {
    $user = User::factory()->create(['role' => 'owner']);
    $this->actingAs($user);

    $conversationId = resolve(ConversationStore::class)->storeConversation($user->id, 'Earlier chat');
    seedAssistantMessage($conversationId, $user->id, 'assistant', 'Previous reply.');

    Livewire::test('admin.assistant')
        ->assertSet('conversationId', $conversationId)
        ->call('startNewConversation')
        ->assertSet('conversationId', null)
        ->assertSet('messages', []);
});

it('mounts the assistant in the admin shell only when enabled and configured', function (): void {
    $this->actingAsAdmin();

    config()->set('site.ai_api_key', '');
    $this->get(route('admin.dashboard'))->assertDontSeeLivewire('admin.assistant');

    config()->set('site.ai_api_key', 'sk-configured');
    $this->get(route('admin.dashboard'))->assertSeeLivewire('admin.assistant');
});

it('forbids sending for users without the assistant ability', function (): void {
    $member = User::factory()->create(['role' => 'member']);
    $this->actingAs($member);

    SiteAssistant::fake();

    Livewire::test('admin.assistant')
        ->call('send', 'Build me a page')
        ->assertForbidden();

    SiteAssistant::assertNeverPrompted();
});
