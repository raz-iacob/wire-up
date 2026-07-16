<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use App\Models\User;
use Livewire\Livewire;

/**
 * @param  array<string, mixed>  $attributes
 */
function memberOnlyPage(string $slug, bool $membersOnly = true, array $attributes = []): Page
{
    $page = Page::factory()->create([
        'metadata' => ['published_locales' => ['en'], 'members_only' => $membersOnly],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'title' => 'Members Area',
        ...$attributes,
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => $slug]);

    return $page;
}

function memberOnlyRecord(string $slug, bool $membersOnly = true): Record
{
    $type = RecordType::factory()->create(['slug_prefix' => 'guides']);
    $record = Record::factory()->create([
        'record_type_id' => $type->id,
        'metadata' => ['published_locales' => ['en'], 'members_only' => $membersOnly],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'title' => ['en' => 'Secret Guide'],
    ]);
    $record->slugs()->create(['locale' => 'en', 'slug' => $slug, 'base_path' => $type->slug_prefix]);

    return $record;
}

it('exposes the members-only flag from metadata', function (): void {
    expect(memberOnlyPage('a')->isMembersOnly())->toBeTrue()
        ->and(memberOnlyPage('b', false)->isMembersOnly())->toBeFalse();
});

it('redirects a guest from a members-only page to login and remembers the target', function (): void {
    memberOnlyPage('members-page');

    $this->get(route('page', 'members-page'))->assertRedirect(route('login'));

    expect(session()->get('url.intended'))->toContain('members-page');
});

it('lets a signed-in member view a members-only page', function (): void {
    memberOnlyPage('members-page', true, ['title' => 'Inner Sanctum']);

    $this->actingAs(User::factory()->member()->create())
        ->get(route('page', 'members-page'))
        ->assertOk()
        ->assertSee('Inner Sanctum');
});

it('lets an admin view a members-only page', function (): void {
    memberOnlyPage('members-page');

    $this->actingAsAdmin()
        ->get(route('page', 'members-page'))
        ->assertOk();
});

it('still serves a normal published page to guests', function (): void {
    memberOnlyPage('open-page', false, ['title' => 'Open House']);

    $this->get(route('page', 'open-page'))
        ->assertOk()
        ->assertSee('Open House');
});

it('redirects a guest from a members-only record to login', function (): void {
    memberOnlyRecord('secret-guide');

    $this->get(route('record', ['recordType' => 'guides', 'slug' => 'secret-guide']))
        ->assertRedirect(route('login'));
});

it('lets a signed-in member view a members-only record', function (): void {
    memberOnlyRecord('secret-guide');

    $this->actingAs(User::factory()->member()->create())
        ->get(route('record', ['recordType' => 'guides', 'slug' => 'secret-guide']))
        ->assertOk()
        ->assertSee('Secret Guide');
});

it('hides the markdown representation of a members-only page from guests', function (): void {
    memberOnlyPage('members-page');

    $this->get(route('page', 'members-page'), ['Accept' => 'text/markdown'])->assertNotFound();
});

it('serves the markdown representation of a members-only page to a member', function (): void {
    memberOnlyPage('members-page', true, ['title' => 'Inner Sanctum']);

    $this->actingAs(User::factory()->member()->create())
        ->get(route('page', 'members-page'), ['Accept' => 'text/markdown'])
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
});

it('marks a members-only page as noindex', function (): void {
    memberOnlyPage('members-page');

    $this->actingAs(User::factory()->member()->create())
        ->get(route('page', 'members-page'))
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

it('leaves members-only content out of the sitemap', function (): void {
    memberOnlyPage('hidden-page');
    memberOnlyRecord('hidden-guide');
    memberOnlyPage('listed-page', false);

    $this->get(route('sitemap'))
        ->assertOk()
        ->assertDontSee('hidden-page')
        ->assertDontSee('hidden-guide')
        ->assertSee('listed-page');
});

it('persists the members-only flag from the page editor', function (): void {
    $page = Page::factory()->create(['status' => ContentStatus::DRAFT]);
    $this->actingAsAdmin();

    Livewire::test('pages::admin.pages-edit', ['page' => $page])
        ->set('status', ContentStatus::PUBLISHED)
        ->set('title.en', 'Members Area')
        ->set('slugs.en', 'members-area')
        ->set('members_only', true)
        ->call('update')
        ->assertHasNoErrors();

    expect($page->refresh()->isMembersOnly())->toBeTrue();
});

it('shows the members-only control in the editors only when sign-ups are on', function (): void {
    $page = Page::factory()->create();
    $type = RecordType::factory()->create(['key' => 'guide', 'slug_prefix' => 'guides']);
    $record = Record::factory()->create(['record_type_id' => $type->id, 'title' => ['en' => 'Guide']]);
    $record->setSlugs();
    $this->actingAsAdmin();

    config()->set('site.allow_registration', false);
    Livewire::test('pages::admin.pages-edit', ['page' => $page])->assertDontSee(__('Members only'));
    Livewire::test('pages::admin.records-edit', ['recordType' => $type, 'record' => $record])->assertDontSee(__('Members only'));

    config()->set('site.allow_registration', true);
    Livewire::test('pages::admin.pages-edit', ['page' => $page])->assertSee(__('Members only'));
    Livewire::test('pages::admin.records-edit', ['recordType' => $type, 'record' => $record])->assertSee(__('Members only'));
});

it('hydrates the members-only flag in the page editor', function (): void {
    $page = Page::factory()->create(['metadata' => ['members_only' => true]]);
    $this->actingAsAdmin();

    Livewire::test('pages::admin.pages-edit', ['page' => $page])
        ->assertSet('members_only', true);
});

it('persists the members-only flag from the record editor', function (): void {
    $type = RecordType::factory()->create(['key' => 'guide', 'slug_prefix' => 'guides']);
    $record = Record::factory()->create(['record_type_id' => $type->id, 'title' => ['en' => 'Guide']]);
    $record->setSlugs();
    $this->actingAsAdmin();

    Livewire::test('pages::admin.records-edit', ['recordType' => $type, 'record' => $record])
        ->set('members_only', true)
        ->call('update')
        ->assertHasNoErrors();

    expect($record->refresh()->isMembersOnly())->toBeTrue();
});
