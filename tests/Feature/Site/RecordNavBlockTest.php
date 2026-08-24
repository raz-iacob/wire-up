<?php

declare(strict_types=1);

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;

function navRecord(RecordType $type, string $title): Record
{
    $record = Record::factory()->create([
        'record_type_id' => $type->id,
        'title' => ['en' => $title],
        'data' => ['heading' => ['en' => $title]],
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);

    $record->setSlugs();

    return $record;
}

it('links to the neighbouring records from a prev/next block on a record', function (): void {
    $type = RecordType::factory()->create(['key' => 'guide', 'slug_prefix' => 'guides', 'fields' => []]);
    $first = navRecord($type, 'Install Wire-Up');
    $second = navRecord($type, 'Build a page');
    $third = navRecord($type, 'Publish the site');

    $first->update(['published_at' => now()->subDays(5)]);
    $second->update(['published_at' => now()->subDays(3)]);
    $third->update(['published_at' => now()->subDay()]);

    $second->updateBlocks([
        ['id' => 'new-1', 'type' => BlockType::RECORD_NAV->value, 'content' => []],
    ]);

    $this->get($second->getUrl())
        ->assertOk()
        ->assertSee('Previous')
        ->assertSee('Next')
        ->assertSee('Install Wire-Up')
        ->assertSee('Publish the site')
        ->assertSee($first->getUrl(), false)
        ->assertSee($third->getUrl(), false);
});

it('renders nothing for a prev/next block sitting on a page', function (): void {
    $type = RecordType::factory()->create(['key' => 'guide', 'slug_prefix' => 'guides', 'fields' => []]);
    navRecord($type, 'Build a page');

    $page = Page::factory()->create([
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'plain']);
    $page->updateBlocks([
        ['id' => 'new-1', 'type' => BlockType::RECORD_NAV->value, 'content' => []],
    ]);

    $this->get(route('page', 'plain'))
        ->assertOk()
        ->assertDontSee('Build a page');
});

it('honours custom prev/next labels and hides the titles when asked', function (): void {
    $type = RecordType::factory()->create(['key' => 'guide', 'slug_prefix' => 'guides', 'fields' => []]);
    $first = navRecord($type, 'Install Wire-Up');
    $second = navRecord($type, 'Build a page');
    $first->update(['published_at' => now()->subDays(5)]);
    $second->update(['published_at' => now()->subDays(3)]);

    $second->updateBlocks([
        ['id' => 'new-1', 'type' => BlockType::RECORD_NAV->value, 'content' => [
            'previousLabel' => ['en' => 'Back'],
            'showTitles' => false,
        ]],
    ]);

    $this->get($second->getUrl())
        ->assertOk()
        ->assertSee('Back')
        ->assertDontSee('Install Wire-Up');
});
