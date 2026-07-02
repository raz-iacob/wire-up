<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Enums\MediaType;
use App\Models\Category;
use App\Models\Locale;
use App\Models\Media;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use Illuminate\Support\Facades\Storage;

/**
 * @param  array<int, array<string, mixed>>  $fields
 */
function recordType(array $fields = [], string $prefix = 'products'): RecordType
{
    return RecordType::factory()->create(['slug_prefix' => $prefix, 'fields' => $fields]);
}

function productRecordType(): RecordType
{
    return RecordType::factory()->create([
        'key' => 'product',
        'slug_prefix' => 'products',
        'fields' => [
            ['key' => 'heading', 'type' => 'text', 'label' => ['en' => 'Title'], 'translatable' => true, 'prefills' => 'title'],
            ['key' => 'overview', 'type' => 'rich-text', 'label' => ['en' => 'Description'], 'translatable' => true, 'prefills' => 'description'],
            ['key' => 'current_price', 'type' => 'money', 'label' => ['en' => 'Current price'], 'translatable' => false],
            ['key' => 'regular_price', 'type' => 'money', 'label' => ['en' => 'Regular price'], 'translatable' => false],
            ['key' => 'gallery', 'type' => 'media-gallery', 'label' => ['en' => 'Gallery'], 'translatable' => false],
        ],
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function publishRecord(RecordType $type, string $slug, array $attributes = []): Record
{
    $record = Record::factory()->create([
        'record_type_id' => $type->id,
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        ...$attributes,
    ]);

    $record->slugs()->create(['locale' => 'en', 'slug' => $slug, 'base_path' => $type->slug_prefix]);

    return $record;
}

it('renders a published record detail page', function (): void {
    publishRecord(recordType(), 'widget', ['title' => ['en' => 'Super Widget'], 'description' => ['en' => 'The best widget.']]);

    $this->get(route('record', ['recordType' => 'products', 'slug' => 'widget']))
        ->assertOk()
        ->assertSeeLivewire('pages::record')
        ->assertSee('Super Widget')
        ->assertSee('The best widget.');
});

it('renders the product layout with category, heading, description, price and computed discount', function (): void {
    Storage::fake(config('filesystems.media'));

    $type = productRecordType();
    $category = Category::factory()->create(['name' => ['en' => 'Sneaker Company']]);

    $record = publishRecord($type, 'fall-sneakers', [
        'title' => ['en' => 'SEO Title'],
        'data' => [
            'heading' => ['en' => 'Fall Limited Edition Sneakers'],
            'overview' => ['en' => '<p>These low-profile sneakers are your perfect casual wear companion.</p>'],
            'current_price' => 125,
            'regular_price' => 250,
        ],
    ]);

    $record->categories()->attach($category->id);

    $image = Media::factory()->create(['type' => MediaType::IMAGE]);
    $record->media()->attach($image->id, ['role' => 'gallery', 'locale' => 'en', 'position' => 0, 'crop' => ['default' => ['w' => 900, 'h' => 900]]]);

    $this->get(route('record', ['recordType' => 'products', 'slug' => 'fall-sneakers']))
        ->assertOk()
        ->assertSee('Fall Limited Edition Sneakers')
        ->assertSee('These low-profile sneakers')
        ->assertSee('50%')
        ->assertSee('125')
        ->assertSee('250');
});

it('includes videos in the product gallery', function (): void {
    Storage::fake(config('filesystems.media'));

    $type = productRecordType();
    $record = publishRecord($type, 'video-product', [
        'title' => ['en' => 'Video Product'],
        'data' => ['heading' => ['en' => 'Video Product'], 'current_price' => 10],
    ]);

    $image = Media::factory()->create(['type' => MediaType::IMAGE]);
    $video = Media::factory()->create(['type' => MediaType::VIDEO, 'source' => 'media/clip.mp4', 'mime_type' => 'video/mp4', 'thumbnail' => 'media/clip_thumb.jpeg']);

    $record->media()->attach($image->id, ['role' => 'gallery', 'locale' => 'en', 'position' => 0]);
    $record->media()->attach($video->id, ['role' => 'gallery', 'locale' => 'en', 'position' => 1]);

    $this->get(route('record', ['recordType' => 'products', 'slug' => 'video-product']))
        ->assertOk()
        ->assertSee('clip.mp4');
});

it('uses the record title as the product heading when the heading field is empty', function (): void {
    $type = productRecordType();

    publishRecord($type, 'no-heading', ['title' => ['en' => 'Fallback Name'], 'data' => ['current_price' => 40]]);

    $this->get(route('record', ['recordType' => 'products', 'slug' => 'no-heading']))
        ->assertOk()
        ->assertSee('Fallback Name');
});

it('returns 404 for a draft record', function (): void {
    publishRecord(recordType(), 'hidden', ['status' => ContentStatus::DRAFT, 'published_at' => null]);

    $this->get(route('record', ['recordType' => 'products', 'slug' => 'hidden']))->assertNotFound();
});

it('returns 404 for a scheduled record with a future publish date', function (): void {
    publishRecord(recordType(), 'soon', ['published_at' => now()->addWeek()]);

    $this->get(route('record', ['recordType' => 'products', 'slug' => 'soon']))->assertNotFound();
});

it('returns 404 for an unknown record type prefix', function (): void {
    publishRecord(recordType(), 'widget');

    $this->get(route('record', ['recordType' => 'nonexistent', 'slug' => 'widget']))->assertNotFound();
});

it('does not resolve a record under a different type prefix', function (): void {
    recordType([], 'services');
    publishRecord(recordType([], 'products'), 'widget', ['title' => ['en' => 'Widget']]);

    $this->get(route('record', ['recordType' => 'services', 'slug' => 'widget']))->assertNotFound();
});

it('returns 404 for a record not published in the current locale', function (): void {
    Locale::query()->where('code', 'fr')->update(['active' => true]);
    cache()->forget('site-locales');

    publishRecord(recordType(), 'fr-only', ['metadata' => ['published_locales' => ['fr']]]);

    $this->get(route('record', ['recordType' => 'products', 'slug' => 'fr-only']))->assertNotFound();
});

it('does not let the record catch-all shadow admin routes', function (): void {
    $this->actingAsAdmin();

    $this->get('/admin/pages')->assertOk();
    $this->get('/admin/settings/general')->assertOk();
    $this->get(route('admin.users-index'))->assertOk();
});

it('routes single-segment slugs to pages and two-segment slugs to records', function (): void {
    publishRecord(recordType(), 'widget', ['title' => ['en' => 'Widget Detail']]);

    $page = Page::factory()->create([
        'title' => 'Products Landing',
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'products']);

    $this->get(route('page', 'products'))->assertOk()->assertSee('Products Landing');
    $this->get(route('record', ['recordType' => 'products', 'slug' => 'widget']))->assertOk()->assertSee('Widget Detail');
});
