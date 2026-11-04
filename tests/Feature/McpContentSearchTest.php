<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Mcp\Servers\WireUpServer;
use App\Mcp\Tools\ReplaceTextTool;
use App\Mcp\Tools\SearchContentTool;
use App\Models\Locale;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;

/**
 * @return array{page: Page, other: Page, record: Record, body: int}
 */
function searchFixtures(): array
{
    Locale::query()->where('code', 'nl')->update(['active' => true]);
    cache()->forget('site-locales');

    $page = Page::factory()->create(['title' => 'About Acme-Co', 'description' => 'Acme-Co builds sites.', 'status' => ContentStatus::PUBLISHED, 'published_at' => now()->subDay()]);
    $page->translations()->updateOrCreate(['key' => 'title', 'locale' => 'nl'], ['body' => 'Over Acme-Co']);
    $page->updateBlocks([
        ['type' => 'rich-text', 'content' => ['body' => [
            'en' => '<p>Acme-Co is great. <a href="https://acme-co.test/acme-co">Visit Acme-Co</a></p>',
            'nl' => '<p>Acme-Co is geweldig.</p>',
        ]]],
        ['type' => 'feature-cards', 'content' => ['items' => [['id' => 'c-1', 'title' => ['en' => 'Acme-Co cards']]]]],
    ]);

    $type = RecordType::factory()->create(['key' => 'guide', 'fields' => [
        ['key' => 'summary', 'type' => 'textarea', 'label' => ['en' => 'Summary'], 'translatable' => true],
        ['key' => 'tagline', 'type' => 'text', 'label' => ['en' => 'Tagline'], 'translatable' => false],
        ['key' => 'price', 'type' => 'number', 'label' => ['en' => 'Price'], 'translatable' => false],
    ]]);
    $record = Record::factory()->create([
        'record_type_id' => $type->id,
        'title' => 'Acme-Co guide',
        'data' => ['summary' => ['en' => 'Learn Acme-Co'], 'tagline' => 'Made by Acme-Co', 'price' => 5],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);

    $other = Page::factory()->create(['title' => 'Contact']);

    return ['page' => $page->refresh(), 'other' => $other, 'record' => $record, 'body' => (int) $page->blocks()->value('id')];
}

it('finds text in titles, descriptions, blocks and record fields in every language, never inside tags', function (): void {
    $fixtures = searchFixtures();

    WireUpServer::tool(SearchContentTool::class, ['find' => 'Acme-Co'])
        ->assertOk()
        ->assertSee([
            '"matches":10',
            '"places":9',
            '"truncated":false',
            '"key":"blocks.'.$fixtures['body'].'.body","locale":"en","count":2,"excerpt":"Acme-Co is great. Visit Acme-Co"',
            '"key":"title","locale":"nl"',
            '"key":"data.tagline","locale":"en","count":1',
            '"key":"data.summary"',
            'replace-text',
        ])
        ->assertDontSee('data.price');

    WireUpServer::tool(SearchContentTool::class, ['find' => 'acme-co', 'ignore_case' => true])
        ->assertOk()
        ->assertSee('"matches":10');
});

it('narrows a search by language, by page or record and by how many places to list', function (): void {
    $fixtures = searchFixtures();

    WireUpServer::tool(SearchContentTool::class, ['find' => 'Acme-Co', 'locale' => 'nl'])->assertSee('"matches":2');
    WireUpServer::tool(SearchContentTool::class, ['find' => 'Acme-Co', 'records' => [$fixtures['record']->id]])->assertSee('"matches":3');
    WireUpServer::tool(SearchContentTool::class, ['find' => 'Acme-Co', 'pages' => [$fixtures['other']->id]])
        ->assertSee(['"matches":0', 'Nothing matched.']);
    WireUpServer::tool(SearchContentTool::class, ['find' => 'Acme-Co', 'limit' => 1])
        ->assertSee(['"matches":10', '"places":9', '"truncated":true'])
        ->assertDontSee('"key":"title","locale":"nl"');
});

it('replaces visible text across pages and records, keeping links and publication', function (): void {
    $fixtures = searchFixtures();
    $revisions = $fixtures['page']->revisions()->count();

    WireUpServer::tool(ReplaceTextTool::class, ['find' => 'Acme-Co', 'replace' => 'Wire Up'])
        ->assertOk()
        ->assertSee(['"replaced":10', '"title":"About Wire Up"', '"replacements":7', '"replacements":3']);

    $page = $fixtures['page']->refresh();
    $record = $fixtures['record']->refresh();

    expect($page->translationsFor('title'))->toBe(['en' => 'About Wire Up', 'nl' => 'Over Wire Up'])
        ->and($page->description)->toBe('Wire Up builds sites.')
        ->and($page->blocks->first()->content['body']['en'])->toBe('<p>Wire Up is great. <a href="https://acme-co.test/acme-co">Visit Wire Up</a></p>')
        ->and($page->blocks->last()->content['items'][0]['title'])->toBe(['en' => 'Wire Up cards'])
        ->and($page->computed_status)->toBe(ContentStatus::PUBLISHED)
        ->and($page->revisions()->count())->toBeGreaterThan($revisions)
        ->and($record->title)->toBe('Wire Up guide')
        ->and($record->data)->toMatchArray(['summary' => ['en' => 'Learn Wire Up'], 'tagline' => 'Made by Wire Up', 'price' => 5])
        ->and($record->computed_status)->toBe(ContentStatus::PUBLISHED);
});

it('replaces only in the language and the pages it is given', function (): void {
    $fixtures = searchFixtures();

    WireUpServer::tool(ReplaceTextTool::class, ['find' => 'Acme-Co', 'replace' => 'Wire Up', 'locale' => 'nl', 'pages' => [$fixtures['page']->id]])
        ->assertOk()
        ->assertSee('"replaced":2');

    expect($fixtures['page']->refresh()->translationsFor('title'))->toBe(['en' => 'About Acme-Co', 'nl' => 'Over Wire Up'])
        ->and($fixtures['page']->blocks->first()->content['body']['nl'])->toBe('<p>Wire Up is geweldig.</p>')
        ->and($fixtures['record']->refresh()->title)->toBe('Acme-Co guide');
});

it('reports a replacement that changes nothing', function (): void {
    searchFixtures();

    WireUpServer::tool(ReplaceTextTool::class, ['find' => 'Acme-Co', 'replace' => 'Acme-Co'])
        ->assertHasErrors(['"find" and "replace" are the same, so there is nothing to change.']);

    WireUpServer::tool(ReplaceTextTool::class, ['find' => 'Globex', 'replace' => ''])
        ->assertOk()
        ->assertSee(['"replaced":0', 'Nothing matched, so nothing changed.']);
});

it('validates what to look for', function (string $tool, array $arguments, string $message): void {
    WireUpServer::tool($tool, $arguments)->assertHasErrors([$message]);
})->with([
    'short find' => [SearchContentTool::class, ['find' => 'a'], '"find" must be at least 2 characters.'],
    'unknown language' => [SearchContentTool::class, ['find' => 'Acme', 'locale' => 'xx'], '"locale" must be one of the site\'s languages: en'],
    'page ids' => [SearchContentTool::class, ['find' => 'Acme', 'pages' => ['about']], '"pages" must be a list of page ids.'],
    'limit' => [SearchContentTool::class, ['find' => 'Acme', 'limit' => 500], '"limit" must be a whole number from 1 to 200.'],
    'no replacement' => [ReplaceTextTool::class, ['find' => 'Acme'], 'Pass "replace": the new text.'],
]);
