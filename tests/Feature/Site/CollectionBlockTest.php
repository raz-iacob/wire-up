<?php

declare(strict_types=1);

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Enums\MediaType;
use App\Models\Media;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use App\Services\SettingsService;
use Livewire\Livewire;

function collectionRecord(RecordType $type, string $title): Record
{
    $record = Record::factory()->create([
        'record_type_id' => $type->id,
        'title' => ['en' => $title],
        'data' => ['heading' => ['en' => $title], 'overview' => ['en' => "<p>Overview of {$title}.</p>"]],
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);

    $record->setSlugs();

    return $record;
}

/**
 * @param  array<string, mixed>  $content
 */
function collectionPage(int $typeId, array $content = []): string
{
    $page = Page::factory()->create([
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);

    $page->slugs()->create(['locale' => 'en', 'slug' => 'listing']);

    $page->blocks()->create([
        'type' => 'collection',
        'position' => 0,
        'content' => [
            'recordTypeId' => $typeId,
            'source' => 'latest',
            'limit' => 12,
            'layout' => 'grid',
            'columns' => 3,
            'showImage' => true,
            ...$content,
        ],
    ]);

    return '/listing';
}

it('renders published records in a grid collection block', function (): void {
    $type = RecordType::factory()->create(['key' => 'post', 'slug_prefix' => 'posts', 'fields' => []]);
    collectionRecord($type, 'First Post');
    collectionRecord($type, 'Second Post');

    $this->get(collectionPage($type->id))
        ->assertOk()
        ->assertSee('First Post')
        ->assertSee('Second Post')
        ->assertSee('/posts/', false);
});

it('renders the list layout', function (): void {
    $type = RecordType::factory()->create(['key' => 'post', 'slug_prefix' => 'posts', 'fields' => []]);
    collectionRecord($type, 'Listed Post');

    $this->get(collectionPage($type->id, ['layout' => 'list']))
        ->assertOk()
        ->assertSee('Listed Post');
});

it('renders the carousel layout with a scroll track and nav controls', function (): void {
    $type = RecordType::factory()->create(['key' => 'post', 'slug_prefix' => 'posts', 'fields' => []]);
    collectionRecord($type, 'First Slide');
    collectionRecord($type, 'Second Slide');

    $this->get(collectionPage($type->id, ['layout' => 'carousel']))
        ->assertOk()
        ->assertSee('First Slide')
        ->assertSee('Second Slide')
        ->assertSee('x-ref="track"', false)
        ->assertSee('snap-x', false);
});

it('places the view-more button in the carousel header', function (): void {
    $type = RecordType::factory()->create(['key' => 'post', 'slug_prefix' => 'posts', 'fields' => []]);
    collectionRecord($type, 'Slide One');

    $this->get(collectionPage($type->id, [
        'layout' => 'carousel',
        'button' => ['enabled' => true, 'text' => ['en' => 'Browse all'], 'link' => ['type' => 'url', 'value' => 'https://example.com/all', 'newTab' => false]],
    ]))
        ->assertOk()
        ->assertSee('Browse all')
        ->assertSee('https://example.com/all', false);
});

it('displays selected extra fields after the description', function (): void {
    $type = RecordType::factory()->create([
        'key' => 'product',
        'slug_prefix' => 'products',
        'fields' => [
            ['key' => 'heading', 'type' => 'text', 'prefills' => 'title', 'translatable' => true],
            ['key' => 'overview', 'type' => 'rich-text', 'prefills' => 'description', 'translatable' => true],
            ['key' => 'current_price', 'type' => 'money', 'label' => ['en' => 'Price'], 'column' => true],
            ['key' => 'sku', 'type' => 'text', 'label' => ['en' => 'SKU']],
        ],
    ]);

    $record = Record::factory()->create([
        'record_type_id' => $type->id,
        'title' => ['en' => 'Priced Widget'],
        'data' => ['heading' => ['en' => 'Priced Widget'], 'overview' => ['en' => '<p>A widget.</p>'], 'current_price' => 20, 'sku' => 'SKU-42'],
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);
    $record->setSlugs();

    $expectedPrice = SettingsService::current()->formatMoney(20);

    $this->get(collectionPage($type->id, ['fields' => ['current_price', 'sku']]))
        ->assertOk()
        ->assertSee('Priced Widget')
        ->assertSee($expectedPrice)
        ->assertSee('SKU-42');
});

it('renders the extra-fields picker in the editor once a type is chosen', function (): void {
    $type = RecordType::factory()->create([
        'key' => 'product',
        'slug_prefix' => 'products',
        'fields' => [
            ['key' => 'current_price', 'type' => 'money', 'label' => ['en' => 'Price']],
        ],
    ]);

    $page = Page::factory()->create();
    $page->blocks()->create([
        'type' => 'collection',
        'position' => 0,
        'content' => [...BlockType::COLLECTION->defaultContent(), 'recordTypeId' => $type->id],
    ]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.pages-edit', ['page' => $page])
        ->assertOk()
        ->assertSee('Extra fields to show');
});

it('omits draft records from a collection block', function (): void {
    $type = RecordType::factory()->create(['key' => 'post', 'slug_prefix' => 'posts', 'fields' => []]);
    collectionRecord($type, 'Shown Post');
    Record::factory()->create([
        'record_type_id' => $type->id,
        'title' => ['en' => 'Hidden Draft'],
        'status' => ContentStatus::DRAFT,
        'published_at' => null,
    ]);

    $this->get(collectionPage($type->id))
        ->assertOk()
        ->assertSee('Shown Post')
        ->assertDontSee('Hidden Draft');
});

it('renders the collection block controls (incl. the image toggle) in the editor', function (): void {
    $page = Page::factory()->create();
    $page->blocks()->create([
        'type' => 'collection',
        'position' => 0,
        'content' => BlockType::COLLECTION->defaultContent(),
    ]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.pages-edit', ['page' => $page])
        ->assertOk()
        ->assertSee('Content type')
        ->assertSee("Show each record's image")
        ->assertSee('Maximum records')
        ->assertSee('Carousel');
});

it('renders the optional view-more button below the records', function (): void {
    $type = RecordType::factory()->create(['key' => 'post', 'slug_prefix' => 'posts', 'fields' => []]);
    collectionRecord($type, 'A Post');

    $this->get(collectionPage($type->id, [
        'button' => ['enabled' => true, 'text' => ['en' => 'View all'], 'link' => ['type' => 'url', 'value' => 'https://example.com/all', 'newTab' => false]],
    ]))
        ->assertOk()
        ->assertSee('View all')
        ->assertSee('https://example.com/all', false);
});

it('shows record images when the toggle is on', function (): void {
    $type = RecordType::factory()->create(['key' => 'post', 'slug_prefix' => 'posts', 'fields' => [['key' => 'photo', 'type' => 'photo', 'translatable' => false]]]);
    $record = collectionRecord($type, 'Imaged Post');
    $image = Media::factory()->create(['type' => MediaType::IMAGE, 'source' => 'media/card.jpg']);
    $record->media()->attach($image->id, ['role' => 'photo', 'locale' => 'en', 'position' => 0]);

    $this->get(collectionPage($type->id, ['showImage' => true]))
        ->assertOk()
        ->assertSee('media/card.jpg', false);
});

it('hides record images when the toggle is off', function (): void {
    $type = RecordType::factory()->create(['key' => 'post', 'slug_prefix' => 'posts', 'fields' => [['key' => 'photo', 'type' => 'photo', 'translatable' => false]]]);
    $record = collectionRecord($type, 'Imaged Post');
    $image = Media::factory()->create(['type' => MediaType::IMAGE, 'source' => 'media/card.jpg']);
    $record->media()->attach($image->id, ['role' => 'photo', 'locale' => 'en', 'position' => 0]);

    $this->get(collectionPage($type->id, ['showImage' => false]))
        ->assertOk()
        ->assertDontSee('media/card.jpg', false);
});
