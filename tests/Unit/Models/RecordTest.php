<?php

declare(strict_types=1);

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Enums\MediaType;
use App\Models\Locale;
use App\Models\Media;
use App\Models\Record;
use App\Models\RecordType;

it('casts json and enum columns', function (): void {
    $record = Record::factory()->create([
        'data' => ['price' => 42],
        'status' => ContentStatus::PUBLISHED,
    ]);

    expect($record->data)->toBe(['price' => 42])
        ->and($record->status)->toBe(ContentStatus::PUBLISHED)
        ->and($record->metadata)->toBeArray();
});

it('belongs to a record type', function (): void {
    $type = RecordType::factory()->create();
    $record = Record::factory()->create(['record_type_id' => $type->id]);

    expect($record->recordType->id)->toBe($type->id);
});

it('resolves title and description via translations', function (): void {
    $record = Record::factory()->create([
        'title' => ['en' => 'Hello'],
        'description' => ['en' => 'World'],
    ]);

    expect($record->title)->toBe('Hello')
        ->and($record->description)->toBe('World');
});

it('namespaces its slug under the record type prefix', function (): void {
    $type = RecordType::factory()->create(['slug_prefix' => 'projects']);
    $record = Record::factory()->create(['record_type_id' => $type->id, 'title' => ['en' => 'Big Build']]);

    $record->setSlugs();

    $this->assertDatabaseHas('slugs', [
        'slug' => 'big-build',
        'base_path' => 'projects',
        'sluggable_type' => 'record',
        'sluggable_id' => $record->id,
    ]);
});

it('reports computed status and the published scope', function (): void {
    $type = RecordType::factory()->create();

    $published = Record::factory()->create(['record_type_id' => $type->id, 'status' => ContentStatus::PUBLISHED, 'published_at' => now()->subDay()]);
    $future = Record::factory()->create(['record_type_id' => $type->id, 'status' => ContentStatus::PUBLISHED, 'published_at' => now()->addDay()]);
    Record::factory()->create(['record_type_id' => $type->id, 'status' => ContentStatus::DRAFT]);

    expect($future->computed_status)->toBe(ContentStatus::SCHEDULED)
        ->and($published->computed_status)->toBe(ContentStatus::PUBLISHED)
        ->and(Record::query()->published()->pluck('id')->all())->toBe([$published->id]);
});

it('reports live status per locale when multilingual', function (): void {
    Locale::query()->where('code', 'fr')->update(['active' => true]);
    cache()->forget('site-locales');

    $record = Record::factory()->create([
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'metadata' => ['published_locales' => ['en']],
    ]);

    expect($record->isLiveInLocale('en'))->toBeTrue()
        ->and($record->isLiveInLocale('fr'))->toBeFalse();
});

it('is not live when it is a draft', function (): void {
    $record = Record::factory()->create(['status' => ContentStatus::DRAFT, 'published_at' => null]);

    expect($record->isLiveInLocale())->toBeFalse();
});

it('reads noindex from metadata', function (): void {
    expect(Record::factory()->make(['metadata' => ['noindex' => true]])->isNoindex())->toBeTrue()
        ->and(Record::factory()->make(['metadata' => []])->isNoindex())->toBeFalse();
});

it('builds a namespaced url from its slug prefix', function (): void {
    $type = RecordType::factory()->create(['slug_prefix' => 'projects']);
    $record = Record::factory()->create(['record_type_id' => $type->id, 'title' => ['en' => 'Big Build']]);
    $record->setSlugs();

    expect($record->getUrl())->toBe(url('projects/big-build'));
});

it('resolves shared and translatable field values from data', function (): void {
    $record = Record::factory()->make([
        'data' => [
            'price' => 42,
            'headline' => ['en' => 'English', 'fr' => 'French'],
        ],
    ]);

    expect($record->fieldValue('price', false))->toBe(42)
        ->and($record->fieldValue('headline', true))->toBe('English')
        ->and($record->fieldValue('missing', false))->toBeNull()
        ->and($record->fieldValue('price', true))->toBeNull();
});

it('harvests plain text from the overview field and blocks', function (): void {
    $record = Record::factory()->create([
        'data' => ['overview' => ['en' => '<p>Handmade &amp; local.</p>']],
    ]);

    $record->blocks()->create([
        'type' => BlockType::RICH_TEXT->value,
        'position' => 0,
        'content' => ['body' => ['en' => '<p>Built to last.</p>']],
    ]);

    expect($record->plainText())->toBe('Handmade & local. Built to last.');
});

it('limits the excerpt to the requested length', function (): void {
    $record = Record::factory()->create([
        'data' => ['overview' => ['en' => str_repeat('word ', 60)]],
    ]);

    expect(mb_strlen($record->textExcerpt(20)))->toBeLessThanOrEqual(20);
});

it('returns an empty string when a record has no textual content', function (): void {
    $record = Record::factory()->create(['data' => []]);

    expect($record->plainText())->toBe('');
});

it('builds a primary image url from the first image-bearing field', function (): void {
    $type = RecordType::factory()->create([
        'fields' => [['key' => 'photo', 'type' => 'photo', 'translatable' => false]],
    ]);
    $record = Record::factory()->create(['record_type_id' => $type->id]);
    $image = Media::factory()->create(['type' => MediaType::IMAGE, 'source' => 'media/pic.jpg']);
    $record->media()->attach($image->id, ['role' => 'photo', 'locale' => 'en', 'position' => 0]);
    $record->load('media', 'recordType');

    expect($record->primaryImageUrl())->toContain('media/pic.jpg');
});

it('skips non-image fields and non-image media, and applies a stored crop', function (): void {
    $type = RecordType::factory()->create([
        'fields' => [
            ['key' => 'headline', 'type' => 'text', 'translatable' => true],
            ['key' => 'bogus', 'type' => 'not-a-real-type', 'translatable' => false],
            ['key' => 'gallery', 'type' => 'media-gallery', 'translatable' => false],
        ],
    ]);
    $record = Record::factory()->create(['record_type_id' => $type->id]);

    $video = Media::factory()->create(['type' => MediaType::VIDEO, 'source' => 'media/clip.mp4']);
    $image = Media::factory()->create(['type' => MediaType::IMAGE, 'source' => 'media/hero.jpg']);
    $record->media()->attach($video->id, ['role' => 'gallery', 'locale' => 'en', 'position' => 0]);
    $record->media()->attach($image->id, ['role' => 'gallery', 'locale' => 'en', 'position' => 1, 'crop' => ['default' => ['crop_w' => 400, 'crop_h' => 300, 'crop_x' => 5, 'crop_y' => 10]]]);
    $record->load('media', 'recordType');

    expect($record->primaryImageUrl())
        ->toContain('media/hero.jpg')
        ->toContain('crop=400-300-5-10');
});

it('returns null for the primary image when the record has none', function (): void {
    $type = RecordType::factory()->create([
        'fields' => [['key' => 'photo', 'type' => 'photo', 'translatable' => false]],
    ]);
    $record = Record::factory()->create(['record_type_id' => $type->id])->load('media', 'recordType');

    expect($record->primaryImageUrl())->toBeNull();
});

it('resolves the display heading and excerpt, with SEO fallbacks', function (): void {
    $type = RecordType::factory()->create(['fields' => []]);

    $rich = Record::factory()->create([
        'record_type_id' => $type->id,
        'title' => ['en' => 'SEO Title'],
        'description' => ['en' => 'SEO description.'],
        'data' => ['heading' => ['en' => 'On-page Heading'], 'overview' => ['en' => '<p>Rich overview body.</p>']],
    ]);

    expect($rich->displayHeading())->toBe('On-page Heading')
        ->and($rich->displayExcerpt())->toBe('Rich overview body.');

    $bare = Record::factory()->create([
        'record_type_id' => $type->id,
        'title' => ['en' => 'Just Title'],
        'description' => ['en' => 'Just a description.'],
        'data' => [],
    ]);

    expect($bare->displayHeading())->toBe('Just Title')
        ->and($bare->displayExcerpt())->toBe('Just a description.');
});
