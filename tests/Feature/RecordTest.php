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

it('renders the team member layout with photo, role and bio', function (): void {
    Storage::fake(config('filesystems.media'));

    $type = RecordType::factory()->create([
        'key' => 'team-member',
        'slug_prefix' => 'team',
        'fields' => [
            ['key' => 'heading', 'type' => 'text', 'label' => ['en' => 'Title'], 'translatable' => true, 'prefills' => 'title'],
            ['key' => 'overview', 'type' => 'rich-text', 'label' => ['en' => 'Description'], 'translatable' => true, 'prefills' => 'description'],
            ['key' => 'role', 'type' => 'text', 'label' => ['en' => 'Role'], 'translatable' => true],
            ['key' => 'photo', 'type' => 'photo', 'label' => ['en' => 'Photo'], 'translatable' => false],
        ],
    ]);

    $record = publishRecord($type, 'ava-thompson', [
        'title' => ['en' => 'Ava Thompson'],
        'data' => ['heading' => ['en' => 'Ava Thompson'], 'role' => ['en' => 'Lead Designer'], 'overview' => ['en' => '<p>Ava leads our design practice.</p>']],
    ]);

    $photo = Media::factory()->create(['type' => MediaType::IMAGE]);
    $record->media()->attach($photo->id, ['role' => 'photo', 'locale' => 'en', 'position' => 0]);

    $this->get(route('record', ['recordType' => 'team', 'slug' => 'ava-thompson']))
        ->assertOk()
        ->assertSee('Ava Thompson')
        ->assertSee('Lead Designer')
        ->assertSee('Ava leads our design practice');
});

it('renders the project layout with client, overview and link host', function (): void {
    $type = RecordType::factory()->create([
        'key' => 'project',
        'slug_prefix' => 'projects',
        'fields' => [
            ['key' => 'heading', 'type' => 'text', 'label' => ['en' => 'Title'], 'translatable' => true, 'prefills' => 'title'],
            ['key' => 'overview', 'type' => 'rich-text', 'label' => ['en' => 'Description'], 'translatable' => true, 'prefills' => 'description'],
            ['key' => 'client', 'type' => 'text', 'label' => ['en' => 'Client'], 'translatable' => false],
            ['key' => 'link', 'type' => 'url', 'label' => ['en' => 'Link'], 'translatable' => false],
        ],
    ]);

    publishRecord($type, 'grandview-kids', [
        'title' => ['en' => 'Grandview Kids Rebrand'],
        'data' => [
            'heading' => ['en' => 'Grandview Kids Rebrand'],
            'overview' => ['en' => '<p>A full digital rebrand.</p>'],
            'client' => 'Grandview Kids',
            'link' => 'https://www.example.com/case',
        ],
    ]);

    $this->get(route('record', ['recordType' => 'projects', 'slug' => 'grandview-kids']))
        ->assertOk()
        ->assertSee('Grandview Kids Rebrand')
        ->assertSee('Grandview Kids')
        ->assertSee('A full digital rebrand')
        ->assertSee('example.com');
});

it('renders the event layout with photo, date, location and overview', function (): void {
    Storage::fake(config('filesystems.media'));

    $type = RecordType::factory()->create([
        'key' => 'event',
        'slug_prefix' => 'events',
        'fields' => [
            ['key' => 'heading', 'type' => 'text', 'label' => ['en' => 'Title'], 'translatable' => true, 'prefills' => 'title'],
            ['key' => 'overview', 'type' => 'rich-text', 'label' => ['en' => 'Description'], 'translatable' => true, 'prefills' => 'description'],
            ['key' => 'starts_at', 'type' => 'datetime', 'label' => ['en' => 'Starts'], 'translatable' => false],
            ['key' => 'ends_at', 'type' => 'datetime', 'label' => ['en' => 'Ends'], 'translatable' => false],
            ['key' => 'location', 'type' => 'text', 'label' => ['en' => 'Location'], 'translatable' => false],
            ['key' => 'photo', 'type' => 'photo', 'label' => ['en' => 'Photo'], 'translatable' => false],
        ],
    ]);

    $record = publishRecord($type, 'summer-launch', [
        'title' => ['en' => 'Summer Launch'],
        'data' => [
            'heading' => ['en' => 'Summer Launch'],
            'overview' => ['en' => '<p>An evening unveiling the collection.</p>'],
            'starts_at' => '2026-08-15 18:00:00',
            'ends_at' => '2026-08-15 21:00:00',
            'location' => 'The Warehouse, Toronto',
        ],
    ]);

    $photo = Media::factory()->create(['type' => MediaType::IMAGE]);
    $record->media()->attach($photo->id, ['role' => 'photo', 'locale' => 'en', 'position' => 0]);

    $this->get(route('record', ['recordType' => 'events', 'slug' => 'summer-launch']))
        ->assertOk()
        ->assertSee('Summer Launch')
        ->assertSee('August 15, 2026')
        ->assertSee('The Warehouse, Toronto')
        ->assertSee('An evening unveiling the collection');
});

it('renders a photo in the default layout when the type has one', function (): void {
    Storage::fake(config('filesystems.media'));

    $type = RecordType::factory()->create([
        'key' => 'service',
        'slug_prefix' => 'services',
        'fields' => [
            ['key' => 'heading', 'type' => 'text', 'label' => ['en' => 'Title'], 'translatable' => true, 'prefills' => 'title'],
            ['key' => 'overview', 'type' => 'rich-text', 'label' => ['en' => 'Description'], 'translatable' => true, 'prefills' => 'description'],
            ['key' => 'photo', 'type' => 'photo', 'label' => ['en' => 'Photo'], 'translatable' => false],
        ],
    ]);

    $record = publishRecord($type, 'web-design', [
        'title' => ['en' => 'Web Design'],
        'data' => ['heading' => ['en' => 'Web Design'], 'overview' => ['en' => '<p>We build fast sites.</p>']],
    ]);

    $photo = Media::factory()->create(['type' => MediaType::IMAGE, 'source' => 'media/service-photo.jpg']);
    $record->media()->attach($photo->id, ['role' => 'photo', 'locale' => 'en', 'position' => 0]);

    $this->get(route('record', ['recordType' => 'services', 'slug' => 'web-design']))
        ->assertOk()
        ->assertSee('Web Design')
        ->assertSee('We build fast sites')
        ->assertSee('media/service-photo.jpg');
});

it('returns 404 for a draft record', function (): void {
    publishRecord(recordType(), 'hidden', ['status' => ContentStatus::DRAFT, 'published_at' => null]);

    $this->get(route('record', ['recordType' => 'products', 'slug' => 'hidden']))->assertNotFound();
});

it('lets an admin preview an unpublished record with a notice', function (): void {
    $type = recordType();
    publishRecord($type, 'draft-widget', ['status' => ContentStatus::DRAFT, 'published_at' => null, 'title' => ['en' => 'Draft Widget']]);

    $this->actingAsAdmin()
        ->get(route('record', ['recordType' => 'products', 'slug' => 'draft-widget']))
        ->assertOk()
        ->assertSee('Draft Widget')
        ->assertSee('This record is not published');
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
