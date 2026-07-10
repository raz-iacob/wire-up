<?php

declare(strict_types=1);

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use Carbon\CarbonInterface;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

function searchableType(string $key, string $name): RecordType
{
    return RecordType::factory()->create(['key' => $key, 'slug_prefix' => $key.'s', 'name' => $name, 'fields' => []]);
}

function searchableRecord(RecordType $type, string $title, ?CarbonInterface $publishedAt = null): Record
{
    $record = Record::factory()->create([
        'record_type_id' => $type->id,
        'title' => ['en' => $title],
        'data' => ['heading' => ['en' => $title]],
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => $publishedAt ?? now()->subDay(),
    ]);

    $record->setSlugs();

    return $record;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function searchBlockContent(array $overrides): array
{
    return [...BlockType::SEARCH->defaultContent(), ...$overrides];
}

it('shows the search form but no results before a query is submitted', function (): void {
    $type = searchableType('post', 'Blog posts');
    searchableRecord($type, 'Hormone Therapy Guide');

    Livewire::test('site.record-search', [
        'blockId' => 'b1',
        'content' => searchBlockContent(['sources' => [(string) $type->id]]),
        'placeholder' => 'Search the site',
    ])
        ->assertSee('Search the site')
        ->assertDontSee('Hormone Therapy Guide');
});

it('groups results by type with totals when a query is submitted', function (): void {
    $faq = searchableType('faq', 'Faqs');
    $post = searchableType('post', 'Blog posts');

    searchableRecord($faq, 'Hormone FAQ');
    foreach (range(1, 6) as $i) {
        searchableRecord($post, "Hormone Post {$i}", now()->subDays($i));
    }

    Livewire::test('site.record-search', [
        'blockId' => 'b1',
        'content' => searchBlockContent(['sources' => [(string) $post->id, (string) $faq->id], 'perType' => 4]),
    ])
        ->set('search', 'hormone')
        ->assertSee('6 Blog posts')
        ->assertSee('1 Faqs')
        ->assertSee('Hormone Post 1')
        ->assertDontSee('Hormone Post 5');
});

it('uses the admin-configured label for the current locale instead of the type name', function (): void {
    $type = searchableType('post', 'Blog posts');
    searchableRecord($type, 'Hormone Therapy Guide');

    Livewire::test('site.record-search', [
        'blockId' => 'b1',
        'content' => searchBlockContent([
            'sources' => [(string) $type->id],
            'labels' => [(string) $type->id => ['en' => 'Articles']],
        ]),
    ])
        ->set('search', 'hormone')
        ->assertSee('1 Articles')
        ->assertDontSee('1 Blog posts');
});

it('includes pages as a searchable source', function (): void {
    $type = searchableType('post', 'Blog posts');
    searchableRecord($type, 'Hormone Post');

    $page = Page::factory()->create([
        'title' => ['en' => 'Hormone Health Page'],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'metadata' => ['published_locales' => ['en']],
    ]);
    $page->setSlugs();

    Livewire::test('site.record-search', [
        'blockId' => 'b1',
        'content' => searchBlockContent([
            'sources' => [(string) $type->id, 'pages'],
            'labels' => ['pages' => ['en' => 'Site pages']],
        ]),
    ])
        ->set('search', 'hormone')
        ->assertSee('1 Site pages')
        ->assertSee('Hormone Health Page');
});

it('persists the query from the url so a refresh keeps the search', function (): void {
    $type = searchableType('post', 'Blog posts');
    searchableRecord($type, 'Hormone Therapy Guide');

    Livewire::withQueryParams(['search' => 'hormone'])
        ->test('site.record-search', [
            'blockId' => 'b1',
            'content' => searchBlockContent(['sources' => [(string) $type->id]]),
        ])
        ->assertSet('search', 'hormone')
        ->assertSee('Hormone Therapy Guide');
});

it('ignores a non-string search param from the url without erroring', function (): void {
    $type = searchableType('post', 'Blog posts');
    searchableRecord($type, 'Hormone Therapy Guide');

    Livewire::withQueryParams(['search' => ['array', 'attack']])
        ->test('site.record-search', [
            'blockId' => 'b1',
            'content' => searchBlockContent(['sources' => [(string) $type->id]]),
        ])
        ->assertSet('search', '')
        ->assertDontSee('Hormone Therapy Guide');
});

it('shows an empty state when nothing matches', function (): void {
    $type = searchableType('post', 'Blog posts');
    searchableRecord($type, 'Hormone Therapy');

    Livewire::test('site.record-search', [
        'blockId' => 'b1',
        'content' => searchBlockContent(['sources' => [(string) $type->id]]),
    ])
        ->set('search', 'nonexistentterm')
        ->assertSee('No results')
        ->assertDontSee('Hormone Therapy');
});

it('caps the submitted query length', function (): void {
    $type = searchableType('post', 'Blog posts');

    Livewire::test('site.record-search', [
        'blockId' => 'b1',
        'content' => searchBlockContent(['sources' => [(string) $type->id]]),
    ])
        ->set('search', str_repeat('a', 250))
        ->assertSet('search', str_repeat('a', 100));
});

it('locks the block config props against client tampering', function (): void {
    $type = searchableType('post', 'Blog posts');

    $component = Livewire::test('site.record-search', [
        'blockId' => 'b1',
        'content' => searchBlockContent(['sources' => [(string) $type->id]]),
    ]);

    expect(fn () => $component->set('content', ['sources' => []]))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('renders the search block inside a public page', function (): void {
    $type = searchableType('post', 'Blog posts');
    $page = Page::factory()->create([
        'title' => ['en' => 'Search Page'],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'metadata' => ['published_locales' => ['en']],
    ]);
    $page->setSlugs();
    $page->blocks()->create([
        'type' => 'search',
        'position' => 0,
        'content' => searchBlockContent(['sources' => [(string) $type->id]]),
    ]);

    $this->get(route('page', $page->slug))
        ->assertOk()
        ->assertSeeLivewire('site.record-search');
});
