<?php

declare(strict_types=1);

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Enums\MediaType;
use App\Models\Category;
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

it('renders a true boolean field as a badge on the card instead of Yes text', function (): void {
    $type = RecordType::factory()->create([
        'key' => 'product',
        'slug_prefix' => 'products',
        'fields' => [
            ['key' => 'heading', 'type' => 'text', 'prefills' => 'title', 'translatable' => true],
            ['key' => 'current_price', 'type' => 'money', 'label' => ['en' => 'Price'], 'translatable' => false],
            ['key' => 'sold', 'type' => 'boolean', 'label' => ['en' => 'Sold'], 'translatable' => false],
        ],
    ]);

    $record = Record::factory()->create([
        'record_type_id' => $type->id,
        'title' => ['en' => 'Warp Core'],
        'data' => ['heading' => ['en' => 'Warp Core'], 'current_price' => 3000, 'sold' => true],
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);
    $record->setSlugs();

    $this->get(collectionPage($type->id, ['fields' => ['current_price', 'sold']]))
        ->assertOk()
        ->assertSee('Warp Core')
        ->assertSee(SettingsService::current()->formatMoney(3000))
        ->assertSee('Sold')
        ->assertDontSeeText('Yes');
});

it('omits the badge on the card when the boolean field is false', function (): void {
    $type = RecordType::factory()->create([
        'key' => 'product',
        'slug_prefix' => 'products',
        'fields' => [
            ['key' => 'heading', 'type' => 'text', 'prefills' => 'title', 'translatable' => true],
            ['key' => 'sold', 'type' => 'boolean', 'label' => ['en' => 'Sold'], 'translatable' => false],
        ],
    ]);

    $record = Record::factory()->create([
        'record_type_id' => $type->id,
        'title' => ['en' => 'Green Giant'],
        'data' => ['heading' => ['en' => 'Green Giant'], 'sold' => false],
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);
    $record->setSlugs();

    $this->get(collectionPage($type->id, ['fields' => ['sold']]))
        ->assertOk()
        ->assertSee('Green Giant')
        ->assertDontSee('Sold');
});

it('renders boolean badges on list-layout cards too', function (): void {
    $type = RecordType::factory()->create([
        'key' => 'product',
        'slug_prefix' => 'products',
        'fields' => [
            ['key' => 'heading', 'type' => 'text', 'prefills' => 'title', 'translatable' => true],
            ['key' => 'sold', 'type' => 'boolean', 'label' => ['en' => 'Sold'], 'translatable' => false],
        ],
    ]);

    $record = Record::factory()->create([
        'record_type_id' => $type->id,
        'title' => ['en' => 'Storm Breaker'],
        'data' => ['heading' => ['en' => 'Storm Breaker'], 'sold' => true],
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);
    $record->setSlugs();

    $this->get(collectionPage($type->id, ['layout' => 'list', 'fields' => ['sold']]))
        ->assertOk()
        ->assertSee('Storm Breaker')
        ->assertSee('Sold');
});

it('renders a translatable boolean badge from the active locale value', function (): void {
    $type = RecordType::factory()->create([
        'key' => 'product',
        'slug_prefix' => 'products',
        'fields' => [
            ['key' => 'heading', 'type' => 'text', 'prefills' => 'title', 'translatable' => true],
            ['key' => 'clearance', 'type' => 'boolean', 'label' => ['en' => 'Clearance'], 'translatable' => true],
        ],
    ]);

    $record = Record::factory()->create([
        'record_type_id' => $type->id,
        'title' => ['en' => 'Last One'],
        'data' => ['heading' => ['en' => 'Last One'], 'clearance' => ['en' => true]],
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);
    $record->setSlugs();

    $this->get(collectionPage($type->id, ['fields' => ['clearance']]))
        ->assertOk()
        ->assertSee('Last One')
        ->assertSee('Clearance');
});

it('renders a related collection on a record page and leaves that record out', function (): void {
    $type = RecordType::factory()->create(['key' => 'guide', 'slug_prefix' => 'guides', 'fields' => []]);
    $category = Category::factory()->create();

    $current = collectionRecord($type, 'Install Wire-Up');
    $sibling = collectionRecord($type, 'Build a page');
    $current->categories()->attach($category);
    $sibling->categories()->attach($category);

    $current->updateBlocks([
        ['id' => 'new-1', 'type' => BlockType::COLLECTION->value, 'content' => [
            'source' => 'related',
            'heading' => ['en' => '<p>Related guides</p>'],
        ]],
    ]);

    $this->get($current->getUrl())
        ->assertOk()
        ->assertSee('Related guides')
        ->assertSee('Build a page');
});

it('renders nothing for a related collection sitting on a page', function (): void {
    $type = RecordType::factory()->create(['key' => 'guide', 'slug_prefix' => 'guides', 'fields' => []]);
    collectionRecord($type, 'Build a page');

    $slug = collectionPage($type->id, [
        'source' => 'related',
        'heading' => ['en' => '<p>Related guides</p>'],
    ]);

    $this->get(route('page', $slug))
        ->assertOk()
        ->assertDontSee('Build a page');
});

it('renders a collection block whose content omits the layout key', function (): void {
    $type = RecordType::factory()->create(['key' => 'guide', 'slug_prefix' => 'guides', 'fields' => []]);
    collectionRecord($type, 'Build a page');

    $slug = collectionPage($type->id, [
        'source' => 'latest',
        'recordTypeId' => $type->id,
    ]);

    $this->get(route('page', $slug))
        ->assertOk()
        ->assertSee('Build a page');
});

it('renders a paginated collection block whose content omits the layout key', function (): void {
    $type = RecordType::factory()->create(['key' => 'guide', 'slug_prefix' => 'guides', 'fields' => []]);
    collectionRecord($type, 'Build a page');

    Livewire::test('site.record-collection', [
        'blockId' => 'b1',
        'mode' => 'paged',
        'content' => ['source' => 'latest', 'recordTypeId' => $type->id, 'perPage' => 6],
    ])->assertOk()->assertSee('Build a page');
});

it('leaves collection cards unpainted by default', function (): void {
    $type = RecordType::factory()->create(['key' => 'post', 'slug_prefix' => 'posts', 'fields' => []]);
    collectionRecord($type, 'Plain Card');

    $html = (string) $this->get(collectionPage($type->id))->assertOk()->getContent();

    expect($html)->toContain('Plain Card')
        ->and($html)->not->toContain('background-color:#');
});

it('paints the cards with a chosen background and text colour', function (): void {
    $type = RecordType::factory()->create(['key' => 'post', 'slug_prefix' => 'posts', 'fields' => []]);
    collectionRecord($type, 'Painted Card');

    $html = (string) $this->get(collectionPage($type->id, [
        'cardBg' => '#123456',
        'cardText' => '#ffffff',
    ]))->assertOk()->getContent();

    expect($html)->toContain('background-color:#123456;color:#ffffff');
});

it('paints carousel cards too, but never the list layout', function (string $layout, bool $painted): void {
    $type = RecordType::factory()->create(['key' => 'post', 'slug_prefix' => 'posts', 'fields' => []]);
    collectionRecord($type, 'Card '.$layout);

    $html = (string) $this->get(collectionPage($type->id, [
        'layout' => $layout,
        'cardBg' => '#123456',
    ]))->assertOk()->getContent();

    expect(str_contains($html, 'background-color:#123456'))->toBe($painted);
})->with([
    'carousel' => ['carousel', true],
    'list' => ['list', false],
]);

it('discards a card colour that could break out of the style attribute', function (string $colour): void {
    $type = RecordType::factory()->create(['key' => 'post', 'slug_prefix' => 'posts', 'fields' => []]);
    collectionRecord($type, 'Guarded Card');

    $html = (string) $this->get(collectionPage($type->id, ['cardBg' => $colour]))->assertOk()->getContent();

    expect($html)->toContain('Guarded Card')
        ->and($html)->not->toContain('background-image')
        ->and($html)->not->toContain('background-color:red');
})->with([
    'a second declaration' => 'red;background-image:url(https://evil.test/x.png)',
    'a url' => 'url(https://evil.test/x.png)',
]);

it('keeps a var() card colour, which carries no colon', function (): void {
    $type = RecordType::factory()->create(['key' => 'post', 'slug_prefix' => 'posts', 'fields' => []]);
    collectionRecord($type, 'Token Card');

    expect((string) $this->get(collectionPage($type->id, ['cardBg' => 'var(--wire-card-bg)']))->assertOk()->getContent())
        ->toContain('background-color:var(--wire-card-bg)');
});

it('dims the description by default and stops when asked', function (bool $dim, bool $expected): void {
    $type = RecordType::factory()->create([
        'key' => 'post',
        'slug_prefix' => 'posts',
        'fields' => [
            ['key' => 'heading', 'type' => 'text', 'prefills' => 'title', 'translatable' => true],
            ['key' => 'overview', 'type' => 'rich-text', 'prefills' => 'description', 'translatable' => true],
        ],
    ]);
    collectionRecord($type, 'Dimmed Post');

    $html = (string) $this->get(collectionPage($type->id, ['dimText' => $dim]))->assertOk()->getContent();

    expect(str_contains($html, 'leading-(--wire-body-leading) opacity-80'))->toBe($expected);
})->with([
    'dimmed' => [true, true],
    'full contrast' => [false, false],
]);

function placedWidgetType(): RecordType
{
    $type = RecordType::factory()->create([
        'key' => 'product',
        'slug_prefix' => 'products',
        'fields' => [
            ['key' => 'heading', 'type' => 'text', 'prefills' => 'title', 'translatable' => true],
            ['key' => 'overview', 'type' => 'rich-text', 'prefills' => 'description', 'translatable' => true],
            ['key' => 'sku', 'type' => 'text', 'label' => ['en' => 'SKU']],
        ],
    ]);

    $record = Record::factory()->create([
        'record_type_id' => $type->id,
        'title' => ['en' => 'Placed Widget'],
        'data' => ['heading' => ['en' => 'Placed Widget'], 'overview' => ['en' => '<p>A widget.</p>'], 'sku' => 'SKU-42'],
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);
    $record->setSlugs();

    return $type;
}

it('footers the extra fields below the description by default', function (): void {
    $type = placedWidgetType();

    $html = (string) $this->get(collectionPage($type->id, ['fields' => ['sku']]))->assertOk()->getContent();

    expect($html)->toContain('mt-auto pt-1 font-medium')
        ->and(mb_strpos($html, 'SKU-42'))->toBeGreaterThan(mb_strpos($html, 'A widget.'));
});

it('moves the extra fields under the title', function (): void {
    $type = placedWidgetType();

    $html = (string) $this->get(collectionPage($type->id, ['fields' => ['sku'], 'fieldPosition' => 'under-title']))->assertOk()->getContent();

    expect($html)->not->toContain('mt-auto pt-1 font-medium')
        ->and(mb_strpos($html, 'SKU-42'))->toBeLessThan(mb_strpos($html, 'A widget.'));
});
