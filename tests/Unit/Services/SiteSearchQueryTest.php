<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use App\Services\SiteSearchQuery;

function searchType(string $key): RecordType
{
    return RecordType::factory()->create(['key' => $key, 'slug_prefix' => $key.'s', 'name' => ucfirst($key), 'fields' => []]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function searchRecord(RecordType $type, string $title, array $attributes = []): Record
{
    $record = Record::factory()->create([
        'record_type_id' => $type->id,
        'title' => ['en' => $title],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        ...$attributes,
    ]);

    $record->setSlugs();

    return $record;
}

function searchPage(string $title, string $description = ''): Page
{
    $page = Page::factory()->create([
        'title' => ['en' => $title],
        'description' => ['en' => $description],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'metadata' => ['published_locales' => ['en']],
    ]);

    $page->setSlugs();

    return $page;
}

it('returns nothing for an empty query or no sources', function (): void {
    $type = searchType('post');
    searchRecord($type, 'Hormone Therapy');

    expect((new SiteSearchQuery)->search('', [(string) $type->id], 4))->toBe([])
        ->and((new SiteSearchQuery)->search('hormone', [], 4))->toBe([]);
});

it('groups record matches with a normalized result shape', function (): void {
    $type = searchType('post');
    searchRecord($type, 'Hormone Guide');

    $groups = (new SiteSearchQuery)->search('hormone', [(string) $type->id], 4);

    expect($groups)->toHaveCount(1)
        ->and($groups[0]['key'])->toBe((string) $type->id)
        ->and($groups[0]['defaultLabel'])->toBe('Post')
        ->and($groups[0]['total'])->toBe(1)
        ->and($groups[0]['results'][0])->toHaveKeys(['title', 'excerpt', 'url', 'image'])
        ->and($groups[0]['results'][0]['title'])->toBe('Hormone Guide');
});

it('searches pages by title and description', function (): void {
    searchPage('Hormone Overview', 'Nothing here');
    searchPage('Unrelated', 'A page about hormone balance');
    searchPage('Totally Different', 'Nope');

    $groups = (new SiteSearchQuery)->search('hormone', ['pages'], 10);

    expect($groups)->toHaveCount(1)
        ->and($groups[0]['key'])->toBe('pages')
        ->and($groups[0]['total'])->toBe(2)
        ->and(collect($groups[0]['results'])->pluck('title')->all())
        ->toContain('Hormone Overview', 'Unrelated');
});

it('combines record and page sources in the given order', function (): void {
    $type = searchType('post');
    searchRecord($type, 'Hormone Guide');
    searchPage('Hormone Page');

    $groups = (new SiteSearchQuery)->search('hormone', ['pages', (string) $type->id], 4);

    expect(array_map(fn (array $g): string => $g['key'], $groups))->toBe(['pages', (string) $type->id]);
});

it('excludes unpublished records and pages', function (): void {
    $type = searchType('post');
    searchRecord($type, 'Hormone Published');
    searchRecord($type, 'Hormone Draft', ['status' => ContentStatus::DRAFT, 'published_at' => null]);

    $draftPage = searchPage('Hormone Draft Page');
    $draftPage->update(['status' => ContentStatus::DRAFT, 'published_at' => null]);
    searchPage('Hormone Live Page');

    $groups = (new SiteSearchQuery)->search('hormone', [(string) $type->id, 'pages'], 10);

    expect($groups[0]['total'])->toBe(1)
        ->and($groups[1]['total'])->toBe(1);
});

it('caps results per source but reports the true total', function (): void {
    $type = searchType('post');
    foreach (range(1, 6) as $i) {
        searchRecord($type, "Hormone {$i}", ['published_at' => now()->subDays($i)]);
    }

    $groups = (new SiteSearchQuery)->search('hormone', [(string) $type->id], 4);

    expect($groups[0]['total'])->toBe(6)
        ->and($groups[0]['results'])->toHaveCount(4);
});

it('ignores unknown record type ids', function (): void {
    $type = searchType('post');
    searchRecord($type, 'Hormone Guide');

    $groups = (new SiteSearchQuery)->search('hormone', [(string) $type->id, '999999'], 4);

    expect($groups)->toHaveCount(1);
});

it('ignores non-numeric junk sources', function (): void {
    $type = searchType('post');
    searchRecord($type, 'Hormone Guide');

    $groups = (new SiteSearchQuery)->search('hormone', ['garbage', (string) $type->id], 4);

    expect($groups)->toHaveCount(1)
        ->and($groups[0]['key'])->toBe((string) $type->id);
});

it('skips the pages source when no page matches', function (): void {
    $type = searchType('post');
    searchRecord($type, 'Hormone Guide');
    searchPage('Something Unrelated');

    $groups = (new SiteSearchQuery)->search('hormone', ['pages', (string) $type->id], 4);

    expect(array_map(fn (array $g): string => $g['key'], $groups))->toBe([(string) $type->id]);
});

it('falls back to page body text for the excerpt when there is no description', function (): void {
    $page = searchPage('Hormone Overview', '');

    $groups = (new SiteSearchQuery)->search('hormone', ['pages'], 4);

    expect($groups[0]['results'][0]['excerpt'])->toBe('');
});
