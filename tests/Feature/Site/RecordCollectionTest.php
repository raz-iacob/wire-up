<?php

declare(strict_types=1);

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Models\Block;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

function paginatedType(): RecordType
{
    return RecordType::factory()->create(['key' => 'post', 'slug_prefix' => 'posts', 'fields' => []]);
}

function titledRecord(RecordType $type, string $title, CarbonInterface $publishedAt): Record
{
    $record = Record::factory()->create([
        'record_type_id' => $type->id,
        'title' => ['en' => $title],
        'data' => ['heading' => ['en' => $title]],
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => $publishedAt,
    ]);

    $record->setSlugs();

    return $record;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function paginatedBlock(RecordType $type, array $overrides): Block
{
    $page = Page::factory()->create();

    return $page->blocks()->create([
        'type' => 'collection',
        'position' => 0,
        'content' => [...BlockType::COLLECTION->defaultContent(), 'recordTypeId' => $type->id, ...$overrides],
    ]);
}

it('renders one page of records with pagination controls in paged mode', function (): void {
    $type = paginatedType();
    $one = titledRecord($type, 'Newest Post', now()->subDay());
    $two = titledRecord($type, 'Middle Post', now()->subDays(2));
    $three = titledRecord($type, 'Oldest Post', now()->subDays(3));

    $block = paginatedBlock($type, ['pagination' => 'paged', 'perPage' => 2]);

    Livewire::test('site.record-collection', ['blockId' => (string) $block->id, 'mode' => 'paged', 'content' => $block->content])
        ->assertSee('Newest Post')
        ->assertSee('Middle Post')
        ->assertDontSee('Oldest Post')
        ->call('gotoPage', 2)
        ->assertSee('Oldest Post')
        ->assertDontSee('Newest Post');
});

it('appends records on load more in infinite mode', function (): void {
    $type = paginatedType();
    titledRecord($type, 'Newest Post', now()->subDay());
    titledRecord($type, 'Middle Post', now()->subDays(2));
    titledRecord($type, 'Oldest Post', now()->subDays(3));

    $block = paginatedBlock($type, ['pagination' => 'infinite', 'perPage' => 2]);

    Livewire::test('site.record-collection', ['blockId' => (string) $block->id, 'mode' => 'infinite', 'content' => $block->content])
        ->assertSee('Newest Post')
        ->assertSee('Middle Post')
        ->assertDontSee('Oldest Post')
        ->call('loadMore')
        ->assertSee('Oldest Post');
});

it('renders nothing when the collection has no records', function (): void {
    $type = paginatedType();
    $block = paginatedBlock($type, ['pagination' => 'paged', 'perPage' => 2]);

    Livewire::test('site.record-collection', ['blockId' => (string) $block->id, 'mode' => 'paged', 'content' => $block->content])
        ->assertDontSee('mx-auto max-w-');
});

it('renders a paginated collection in page preview when the block is unsaved (no id)', function (): void {
    $type = paginatedType();
    titledRecord($type, 'Preview Widget', now()->subDay());

    $user = User::factory()->create(['role' => 'owner', 'active' => true]);
    $page = Page::factory()->create();
    $token = 'preview-token';

    Cache::put("page-preview:{$page->id}:{$user->id}:{$token}", [
        'page_id' => $page->id,
        'locale' => 'en',
        'title' => 'Preview',
        'description' => '',
        'blocks' => [[
            'type' => 'collection',
            'position' => 0,
            'content' => [...BlockType::COLLECTION->defaultContent(), 'recordTypeId' => $type->id, 'pagination' => 'infinite', 'perPage' => 2],
        ]],
        'layout' => [],
    ], now()->addMinutes(30));

    $this->actingAs($user)
        ->get(route('admin.pages-preview', ['page' => $page, 'token' => $token]))
        ->assertOk()
        ->assertSeeLivewire('site.record-collection')
        ->assertSee('Preview Widget');
});

it('delegates to the record-collection livewire component when paginated', function (): void {
    $type = paginatedType();
    titledRecord($type, 'Paged Widget', now()->subDay());

    $page = Page::factory()->create([
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'shop']);
    $page->blocks()->create([
        'type' => 'collection',
        'position' => 0,
        'content' => [...BlockType::COLLECTION->defaultContent(), 'recordTypeId' => $type->id, 'pagination' => 'infinite', 'perPage' => 2],
    ]);

    $this->get('/shop')
        ->assertOk()
        ->assertSeeLivewire('site.record-collection')
        ->assertSee('Paged Widget');
});
