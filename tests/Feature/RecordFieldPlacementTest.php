<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Enums\MediaType;
use App\Models\Media;
use App\Models\Record;
use App\Models\RecordType;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

/**
 * @param  array<int, array<string, mixed>>  $fields
 */
function placedType(array $fields, string $key = 'pc_builds', string $prefix = 'builds'): RecordType
{
    return RecordType::factory()->create(['key' => $key, 'slug_prefix' => $prefix, 'fields' => $fields]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function placedRecord(RecordType $type, string $slug, array $attributes = []): Record
{
    $record = Record::factory()->for($type)->create([
        'title' => ['en' => 'Placed Record'],
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        ...$attributes,
    ]);
    $record->slugs()->create(['locale' => 'en', 'slug' => $slug, 'base_path' => $type->slug_prefix]);

    return $record;
}

/**
 * @return array<string, mixed>
 */
function placedField(string $key, string $type, string $placement, bool $translatable = false): array
{
    return ['key' => $key, 'type' => $type, 'label' => ['en' => ucfirst(str_replace('_', ' ', $key))], 'translatable' => $translatable, 'placement' => $placement];
}

it('puts each field in its column, in blueprint order, and leaves hidden fields off the page', function (): void {
    $type = placedType([
        placedField('heading', 'text', 'main', true),
        placedField('overview', 'rich-text', 'main', true),
        placedField('client', 'text', 'sidebar'),
        placedField('budget', 'money', 'hidden'),
    ]);
    placedRecord($type, 'rig', ['data' => [
        'heading' => ['en' => 'Gaming Rig'],
        'overview' => ['en' => '<p>Fast and quiet.</p>'],
        'client' => 'Acme',
        'budget' => 4200,
    ]]);

    $this->get('/builds/rig')
        ->assertOk()
        ->assertSeeInOrder(['data-placement="main"', 'data-field="heading"', 'Gaming Rig', 'data-field="overview"', 'Fast and quiet.', 'data-placement="sidebar"', 'data-field="client"', 'Client', 'Acme'], false)
        ->assertDontSee('data-field="budget"', false)
        ->assertDontSee('4,200');
});

it('formats each kind of value for visitors', function (): void {
    $type = placedType([
        placedField('heading', 'text', 'main', true),
        placedField('website', 'url', 'sidebar'),
        placedField('contact', 'email', 'sidebar'),
        placedField('finish', 'color', 'sidebar'),
        placedField('labels', 'tags', 'sidebar'),
        placedField('extras', 'multi-select', 'sidebar'),
        placedField('in_stock', 'boolean', 'sidebar'),
        placedField('discontinued', 'boolean', 'sidebar'),
        placedField('cost', 'money', 'sidebar'),
        placedField('released', 'date', 'sidebar'),
        placedField('starts_at', 'datetime', 'sidebar'),
        placedField('ends_at', 'datetime', 'hidden'),
        placedField('cores', 'number', 'sidebar'),
        placedField('story', 'textarea', 'main'),
        placedField('fake_link', 'url', 'sidebar'),
        placedField('empty_note', 'text', 'sidebar'),
    ]);
    placedRecord($type, 'values', ['data' => [
        'heading' => ['en' => 'All Values'],
        'website' => 'https://www.example.com/rig',
        'contact' => 'hello@example.com',
        'finish' => '#ff0000',
        'labels' => ['quiet', 'compact'],
        'extras' => ['Wi-Fi'],
        'in_stock' => true,
        'discontinued' => false,
        'cost' => 1999,
        'released' => '2026-06-05',
        'starts_at' => '2026-06-05T19:00',
        'ends_at' => '2026-06-05T21:00',
        'cores' => 16,
        'story' => "Line one\nLine two",
        'fake_link' => 'javascript:alert(1)',
        'empty_note' => '',
    ]]);

    $this->get('/builds/values')
        ->assertOk()
        ->assertSee('href="https://www.example.com/rig"', false)
        ->assertSee('>example.com</a>', false)
        ->assertSee('href="mailto:hello@example.com"', false)
        ->assertSee('background-color: #ff0000', false)
        ->assertSeeInOrder(['quiet', 'compact', 'Wi-Fi'])
        ->assertSeeInOrder(['In stock', 'Yes', 'Discontinued', 'No'])
        ->assertSee(SettingsService::current()->formatMoney(1999))
        ->assertSee('June 5, 2026')
        ->assertSee('June 5, 2026 · 7:00 PM – 9:00 PM')
        ->assertSee('16')
        ->assertSee("Line one\nLine two")
        ->assertDontSee('href="javascript:alert(1)"', false)
        ->assertDontSee('data-field="empty_note"', false);
});

it('shows an event that runs over several days as one range', function (): void {
    $type = placedType([
        placedField('starts_at', 'datetime', 'sidebar'),
        placedField('ends_at', 'datetime', 'hidden'),
    ]);
    placedRecord($type, 'festival', ['data' => ['starts_at' => '2026-06-05T19:00', 'ends_at' => '2026-06-07T18:00']]);

    $this->get('/builds/festival')
        ->assertOk()
        ->assertSee('June 5, 2026 · 7:00 PM – June 7, 2026 · 6:00 PM');
});

it('strips scripts from rich text fields and labels the ones that are not the description', function (): void {
    $type = placedType([
        placedField('overview', 'rich-text', 'main', true),
        placedField('specs', 'rich-text', 'main', true),
    ]);
    placedRecord($type, 'safe', ['data' => [
        'overview' => ['en' => '<p>Intro</p><script>alert(1)</script>'],
        'specs' => ['en' => '<ul><li>32 GB</li></ul>'],
    ]]);

    $this->get('/builds/safe')
        ->assertOk()
        ->assertSee('Intro')
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSeeInOrder(['Specs', '32 GB']);
});

it('shows photos, galleries, videos, audio, documents and linked records', function (): void {
    Storage::fake(config('filesystems.media'));
    $speakers = placedType([], 'speaker', 'speakers');
    placedRecord($speakers, 'ada', ['title' => ['en' => 'Ada Lovelace']]);
    $linked = Record::query()->where('record_type_id', $speakers->id)->firstOrFail();

    $type = placedType([
        placedField('cover', 'photo', 'main'),
        placedField('portrait', 'photo', 'sidebar'),
        placedField('shots', 'media-gallery', 'main'),
        placedField('clip', 'video', 'main'),
        placedField('theme', 'audio', 'main'),
        placedField('brochure', 'document', 'sidebar'),
        ['key' => 'speakers', 'type' => 'relationship', 'label' => ['en' => 'Speakers'], 'record_type' => 'speaker', 'multiple' => true, 'placement' => 'main'],
    ]);
    $record = placedRecord($type, 'media', ['data' => ['speakers' => [$linked->id]]]);

    $attach = function (string $role, Media $media) use ($record): void {
        $record->media()->attach($media->id, ['role' => $role, 'locale' => 'en', 'position' => 0, 'crop' => ['default' => ['crop_w' => 400, 'crop_h' => 300, 'crop_x' => 10, 'crop_y' => 20]]]);
    };
    $attach('cover', Media::factory()->create(['type' => MediaType::IMAGE, 'source' => 'media/cover.jpg']));
    $attach('portrait', Media::factory()->create(['type' => MediaType::IMAGE, 'source' => 'media/portrait.png']));
    $attach('shots', Media::factory()->create(['type' => MediaType::IMAGE, 'source' => 'media/shot.jpg']));
    $attach('clip', Media::factory()->create(['type' => MediaType::VIDEO, 'source' => 'media/clip.mp4', 'mime_type' => 'video/mp4', 'thumbnail' => 'media/clip_thumb.jpg']));
    $attach('theme', Media::factory()->create(['type' => MediaType::AUDIO, 'source' => 'media/theme.mp3']));
    $attach('brochure', Media::factory()->create(['type' => MediaType::DOCUMENT, 'source' => 'media/brochure.pdf', 'filename' => 'brochure.pdf', 'size' => 2048]));

    $this->get('/builds/media')
        ->assertOk()
        ->assertSee('crop=400-300-10-20', false)
        ->assertSee('fm=webp', false)
        ->assertSee('data-field="shots"', false)
        ->assertSee('clip.mp4', false)
        ->assertSee('theme.mp3', false)
        ->assertSee('brochure.pdf')
        ->assertSee(Number::fileSize(2048, precision: 1))
        ->assertSeeInOrder(['Speakers', 'Ada Lovelace']);
});

it('keeps a type saved before placements looking as it did', function (): void {
    $type = placedType([
        ['key' => 'heading', 'type' => 'text', 'label' => ['en' => 'Title'], 'translatable' => true],
        ['key' => 'overview', 'type' => 'rich-text', 'label' => ['en' => 'Description'], 'translatable' => true],
        ['key' => 'cpu', 'type' => 'text', 'label' => ['en' => 'CPU'], 'translatable' => false],
    ]);
    placedRecord($type, 'legacy', ['data' => ['heading' => ['en' => 'Old Rig'], 'overview' => ['en' => '<p>Still here.</p>'], 'cpu' => 'Ryzen 9']]);

    $this->get('/builds/legacy')
        ->assertOk()
        ->assertSee('Old Rig')
        ->assertSee('Still here.')
        ->assertDontSee('Ryzen 9');

    $this->get('/builds/legacy', ['Accept' => 'text/markdown'])
        ->assertOk()
        ->assertSee('**CPU:** Ryzen 9', false);
});

it('leaves a field the owner hid out of the page for agents too', function (): void {
    $type = placedType([
        placedField('overview', 'rich-text', 'hidden', true),
        placedField('cpu', 'text', 'hidden'),
        placedField('gpu', 'text', 'sidebar'),
    ]);
    placedRecord($type, 'secret', ['data' => ['overview' => ['en' => '<p>Internal notes.</p>'], 'cpu' => 'Ryzen 9', 'gpu' => 'RTX 5090']]);

    $this->get('/builds/secret', ['Accept' => 'text/markdown'])
        ->assertOk()
        ->assertSee('**Gpu:** RTX 5090', false)
        ->assertDontSee('Ryzen 9')
        ->assertDontSee('Internal notes.');
});

it('hides the heading visually when the owner hides it, and falls back to the title and description without one', function (): void {
    $hidden = placedType([placedField('heading', 'text', 'hidden', true)]);
    placedRecord($hidden, 'quiet', ['data' => ['heading' => ['en' => 'Quiet Rig']]]);

    $this->get('/builds/quiet')
        ->assertOk()
        ->assertSee('<h1 class="sr-only">Quiet Rig</h1>', false);

    $bare = placedType([], 'bare', 'bare');
    placedRecord($bare, 'plain', ['title' => ['en' => 'Plain Record'], 'description' => ['en' => 'Just a description.']]);

    $this->get('/bare/plain')
        ->assertOk()
        ->assertSeeInOrder(['Plain Record', 'Just a description.']);
});

it('falls back to the description when the overview is empty', function (): void {
    $type = placedType([placedField('overview', 'rich-text', 'main', true)]);
    placedRecord($type, 'no-overview', ['description' => ['en' => 'Shown instead.']]);

    $this->get('/builds/no-overview')
        ->assertOk()
        ->assertSee('data-field="overview"', false)
        ->assertSee('Shown instead.');
});

it('shows the price with the buy box on a sellable type, and the buy box on its own when the price is hidden', function (): void {
    connectStripe();
    $record = sellableRecord(['current_price' => '49.00']);

    $this->get($record->getUrl())
        ->assertOk()
        ->assertSee('data-field="current_price"', false)
        ->assertSee(SettingsService::current()->formatMoney('49.00'))
        ->assertSeeLivewire('site.buy-box');

    $type = $record->recordType;
    $type->update(['fields' => array_map(fn (array $field): array => [...$field, 'placement' => 'hidden'], $type->fields)]);

    $this->get($record->refresh()->getUrl())
        ->assertOk()
        ->assertDontSee('data-field="current_price"', false)
        ->assertSeeLivewire('site.buy-box');
});

it('puts the buy box in the sidebar when the price is hidden and the sidebar has fields', function (): void {
    connectStripe();
    $record = sellableRecord(['current_price' => '49.00', 'sku' => 'ABC-1']);
    $type = $record->recordType;
    $type->update(['fields' => [
        ...array_map(fn (array $field): array => [...$field, 'placement' => 'hidden'], $type->fields),
        placedField('sku', 'text', 'sidebar'),
    ]]);

    $this->get($record->refresh()->getUrl())
        ->assertOk()
        ->assertSeeInOrder(['data-placement="sidebar"', 'ABC-1', 'wire:id'], false);
});

it('lets the sidebar take the full width when the main column has nothing to show', function (): void {
    $type = placedType([
        placedField('heading', 'text', 'sidebar', true),
        placedField('shots', 'media-gallery', 'main'),
        placedField('cpu', 'text', 'sidebar'),
    ]);
    placedRecord($type, 'no-shots', ['data' => ['heading' => ['en' => 'No Shots'], 'cpu' => 'Ryzen 9']]);

    $this->get('/builds/no-shots')
        ->assertOk()
        ->assertSeeInOrder(['data-placement="main"', 'No Shots', 'Ryzen 9'], false)
        ->assertDontSee('data-placement="sidebar"', false)
        ->assertDontSee('md:grid-cols-3', false);
});
