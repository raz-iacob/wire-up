<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Mcp\Servers\WireUpServer;
use App\Mcp\Tools\GetContentStringsTool;
use App\Mcp\Tools\UpdateContentStringsTool;
use App\Models\Locale;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;

function contentActivateNl(): void
{
    Locale::query()->where('code', 'nl')->update(['active' => true]);
    cache()->forget('site-locales');
}

/**
 * @param  array<string, mixed>  $attributes
 */
function contentPage(array $attributes = []): Page
{
    $page = Page::factory()->create([
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'title' => 'About Us',
        'description' => 'Who we are.',
        ...$attributes,
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'company-fixture']);
    $page->blocks()->create([
        'type' => 'rich-text',
        'position' => 0,
        'content' => [
            'heading' => ['en' => 'Welcome'],
            'subheading' => [],
            'align' => 'center',
            'cta' => ['enabled' => true, 'text' => ['en' => 'Learn more'], 'link' => ['type' => 'url', 'value' => '#x', 'newTab' => false]],
            'items' => [['id' => 'a', 'title' => ['en' => 'First']]],
        ],
    ]);

    return $page;
}

function contentBlockId(Page $page): int
{
    return (int) $page->blocks()->value('id');
}

beforeEach(fn () => contentActivateNl());

it('advertises the content-strings tools with their schema', function (): void {
    expect(resolve(GetContentStringsTool::class)->toArray()['name'])->toBe('get-content-strings')
        ->and(resolve(UpdateContentStringsTool::class)->toArray()['inputSchema']['required'])->toBe(['type', 'id', 'locale', 'translations']);
});

it('extracts translatable strings from a page including nested block fields', function (): void {
    $page = contentPage();
    $block = contentBlockId($page);

    WireUpServer::tool(GetContentStringsTool::class, ['type' => 'page', 'id' => $page->id, 'locale' => 'nl'])
        ->assertOk()
        ->assertSee('"key":"title"')
        ->assertSee('"key":"description"')
        ->assertSee('"key":"blocks.'.$block.'.heading"')
        ->assertSee('"key":"blocks.'.$block.'.cta.text"')
        ->assertSee('"key":"blocks.'.$block.'.items.0.title"')
        ->assertSee('Welcome')
        ->assertDontSee('"key":"blocks.'.$block.'.align"');
});

it('saves page translations without overwriting the source locale and publishes the locale', function (): void {
    $page = contentPage();
    $block = contentBlockId($page);

    WireUpServer::tool(UpdateContentStringsTool::class, [
        'type' => 'page',
        'id' => $page->id,
        'locale' => 'nl',
        'translations' => [
            'title' => 'Over ons',
            'description' => 'Wie we zijn.',
            'blocks.'.$block.'.heading' => 'Welkom',
            'blocks.'.$block.'.items.0.title' => 'Eerste',
            'made.up.key' => 'x',
        ],
    ])
        ->assertOk()
        ->assertSee('"applied":4')
        ->assertSee('"published":true')
        ->assertSee('made.up.key');

    $page->refresh()->load('blocks');

    expect($page->translationsFor('title'))->toMatchArray(['en' => 'About Us', 'nl' => 'Over ons'])
        ->and($page->translationsFor('description'))->toMatchArray(['en' => 'Who we are.', 'nl' => 'Wie we zijn.'])
        ->and($page->getSlugsArray()['nl'])->toBe('over-ons')
        ->and($page->published_locales)->toContain('nl');

    $content = $page->blocks->first()->content;
    expect($content['heading'])->toBe(['en' => 'Welcome', 'nl' => 'Welkom'])
        ->and($content['items'][0]['title'])->toBe(['en' => 'First', 'nl' => 'Eerste'])
        ->and($content['align'])->toBe('center');
});

it('keeps a page a draft when translated and leaves the source status untouched', function (): void {
    $page = contentPage(['status' => ContentStatus::DRAFT, 'published_at' => null]);

    WireUpServer::tool(UpdateContentStringsTool::class, [
        'type' => 'page', 'id' => $page->id, 'locale' => 'nl',
        'translations' => ['title' => 'Over ons'],
    ])->assertOk();

    expect($page->refresh()->status)->toBe(ContentStatus::DRAFT);
});

it('preserves a scheduled page when translating', function (): void {
    $page = contentPage(['status' => ContentStatus::PUBLISHED, 'published_at' => now()->addWeek()]);

    WireUpServer::tool(UpdateContentStringsTool::class, [
        'type' => 'page', 'id' => $page->id, 'locale' => 'nl',
        'translations' => ['title' => 'Over ons'],
    ])->assertOk();

    expect($page->refresh()->computed_status)->toBe(ContentStatus::SCHEDULED);
});

it('does not publish the locale when only a block is translated without a title', function (): void {
    $page = Page::factory()->create([
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'title' => 'About',
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'company-two']);
    $page->blocks()->create(['type' => 'rich-text', 'position' => 0, 'content' => ['heading' => ['en' => 'Welcome']]]);
    $block = contentBlockId($page);

    WireUpServer::tool(UpdateContentStringsTool::class, [
        'type' => 'page', 'id' => $page->id, 'locale' => 'nl',
        'translations' => ['blocks.'.$block.'.heading' => 'Welkom'],
    ])
        ->assertOk()
        ->assertSee('"published":false');

    expect($page->refresh()->published_locales)->not->toContain('nl');
});

it('reports applied zero when every key is unknown', function (): void {
    $page = contentPage();

    WireUpServer::tool(UpdateContentStringsTool::class, [
        'type' => 'page', 'id' => $page->id, 'locale' => 'nl',
        'translations' => ['nonsense' => 'x'],
    ])
        ->assertOk()
        ->assertSee('"applied":0');

    expect($page->refresh()->published_locales)->not->toContain('nl');
});

it('translates a record data field and title', function (): void {
    $type = RecordType::factory()->create([
        'slug_prefix' => 'guides',
        'fields' => [['key' => 'overview', 'type' => 'rich-text', 'label' => ['en' => 'Overview'], 'translatable' => true]],
    ]);
    $record = Record::factory()->create([
        'record_type_id' => $type->id,
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'title' => ['en' => 'Guide'],
        'data' => ['overview' => ['en' => 'The overview.']],
    ]);
    $record->setSlugs();

    WireUpServer::tool(GetContentStringsTool::class, ['type' => 'record', 'id' => $record->id, 'locale' => 'nl'])
        ->assertOk()
        ->assertSee('"key":"data.overview"');

    WireUpServer::tool(UpdateContentStringsTool::class, [
        'type' => 'record', 'id' => $record->id, 'locale' => 'nl',
        'translations' => ['title' => 'Gids', 'data.overview' => 'Het overzicht.'],
    ])
        ->assertOk()
        ->assertSee('"applied":2')
        ->assertSee('"published":true');

    $record->refresh();

    expect($record->data['overview'])->toBe(['en' => 'The overview.', 'nl' => 'Het overzicht.'])
        ->and($record->translationsFor('title'))->toMatchArray(['en' => 'Guide', 'nl' => 'Gids'])
        ->and($record->published_locales)->toContain('nl');
});

it('rejects a missing record', function (): void {
    WireUpServer::tool(GetContentStringsTool::class, ['type' => 'record', 'id' => 999, 'locale' => 'nl'])
        ->assertHasErrors(['No record with id 999']);

    WireUpServer::tool(UpdateContentStringsTool::class, ['type' => 'page', 'id' => 999, 'locale' => 'nl', 'translations' => ['title' => 'x']])
        ->assertHasErrors(['No page with id 999']);
});

it('reports when there is no language to translate content into', function (): void {
    Locale::query()->where('code', 'nl')->update(['active' => false]);
    cache()->forget('site-locales');

    WireUpServer::tool(GetContentStringsTool::class, ['type' => 'page', 'id' => 1, 'locale' => 'nl'])
        ->assertHasErrors(['There are no non-default languages active to translate into.']);

    WireUpServer::tool(UpdateContentStringsTool::class, ['type' => 'page', 'id' => 1, 'locale' => 'nl', 'translations' => ['title' => 'x']])
        ->assertHasErrors(['There are no non-default languages active to translate into.']);
});

it('rejects translating into the default or an inactive locale', function (): void {
    $page = contentPage();

    WireUpServer::tool(UpdateContentStringsTool::class, ['type' => 'page', 'id' => $page->id, 'locale' => 'en', 'translations' => ['title' => 'x']])
        ->assertHasErrors(['Unknown or non-translatable locale']);
});
