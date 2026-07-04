<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Enums\MediaType;
use App\Models\Media;
use App\Models\Record;
use App\Models\RecordType;
use App\Services\RecordSchema;
use App\Services\RecordTypePresets;

/**
 * @param  array<string, mixed>  $data
 * @param  array<string, mixed>  $attributes
 * @param  array<int, array<string, mixed>>|null  $fields
 */
function schemaRecord(string $presetKey, array $data = [], array $attributes = [], ?array $fields = null): Record
{
    /** @var array<string, mixed> $preset */
    $preset = RecordTypePresets::find($presetKey);

    $type = RecordType::factory()->create([
        'key' => $preset['key'],
        'slug_prefix' => $preset['slug_prefix'],
        'name' => $preset['name'],
        'fields' => $fields ?? $preset['fields'],
    ]);

    $record = Record::factory()->create([
        'record_type_id' => $type->id,
        'data' => $data,
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        ...$attributes,
    ]);

    return $record->load('recordType', 'media');
}

/**
 * @return array<int, array<string, mixed>>
 */
function schemaNodes(Record $record, string $url = 'https://example.test/x/y', string $description = 'A description.', ?string $fallbackImage = null): array
{
    return new RecordSchema('https://example.test/#organization', $fallbackImage)->nodes($record, $url, $description);
}

it('builds an Article node for a post record', function (): void {
    $record = schemaRecord('post', ['heading' => ['en' => 'Headline']], ['title' => ['en' => 'Big News']]);

    $node = schemaNodes($record)[0];

    expect($node)->toMatchArray([
        '@type' => 'Article',
        '@id' => 'https://example.test/x/y#post',
        'headline' => 'Big News',
        'name' => 'Big News',
        'description' => 'A description.',
        'author' => ['@id' => 'https://example.test/#organization'],
        'publisher' => ['@id' => 'https://example.test/#organization'],
    ])->and($node)->toHaveKeys(['datePublished', 'dateModified']);
});

it('omits the published date on an Article without one', function (): void {
    $record = schemaRecord('post', [], ['title' => ['en' => 'Draft-ish'], 'published_at' => null]);

    expect(schemaNodes($record)[0])->not->toHaveKey('datePublished');
});

it('builds a CreativeWork node with a link for a project record', function (): void {
    $record = schemaRecord('project', ['link' => 'https://client.example'], ['title' => ['en' => 'Case Study']]);

    expect(schemaNodes($record)[0])->toMatchArray([
        '@type' => 'CreativeWork',
        'name' => 'Case Study',
        'url' => 'https://client.example',
        'creator' => ['@id' => 'https://example.test/#organization'],
    ]);
});

it('ignores a non-url project link', function (): void {
    $record = schemaRecord('project', ['link' => 'not-a-url'], ['title' => ['en' => 'Case Study']]);

    expect(schemaNodes($record)[0])->not->toHaveKey('url');
});

it('builds a JobPosting node for a job record', function (): void {
    $record = schemaRecord('job', [
        'employment_type' => ['en' => 'Full-time'],
        'location' => 'Remote',
    ], ['title' => ['en' => 'Engineer']]);

    expect(schemaNodes($record)[0])->toMatchArray([
        '@type' => 'JobPosting',
        'title' => 'Engineer',
        'employmentType' => 'Full-time',
        'jobLocation' => ['@type' => 'Place', 'address' => 'Remote'],
        'hiringOrganization' => ['@id' => 'https://example.test/#organization'],
    ]);
});

it('builds a Service node for a service record', function (): void {
    $record = schemaRecord('service', [], ['title' => ['en' => 'Consulting']]);

    expect(schemaNodes($record)[0])->toMatchArray([
        '@type' => 'Service',
        'name' => 'Consulting',
        'provider' => ['@id' => 'https://example.test/#organization'],
    ]);
});

it('returns no nodes for a record type without a schema mapping', function (): void {
    $type = RecordType::factory()->create(['key' => 'gizmo', 'slug_prefix' => 'gizmos', 'fields' => []]);
    $record = Record::factory()->create([
        'record_type_id' => $type->id,
        'status' => ContentStatus::PUBLISHED,
        'title' => ['en' => 'Widget'],
    ])->load('recordType', 'media');

    expect(schemaNodes($record))->toBe([]);
});

it('omits an Offer and sku when the product fields are absent', function (): void {
    $record = schemaRecord('product', ['heading' => ['en' => 'Bare']], ['title' => ['en' => 'Bare']], fields: []);

    $node = schemaNodes($record)[0];

    expect($node['@type'])->toBe('Product')
        ->and($node)->not->toHaveKey('offers')
        ->and($node)->not->toHaveKey('sku');
});

it('falls back to the regular price when there is no current price', function (): void {
    $record = schemaRecord('product', ['regular_price' => '25'], ['title' => ['en' => 'Deal']]);

    expect(schemaNodes($record)[0]['offers'])->toMatchArray(['@type' => 'Offer', 'price' => '25']);
});

it('collects record images and skips videos, preferring media over the fallback', function (): void {
    $record = schemaRecord('product', [], ['title' => ['en' => 'Gallery Item']]);

    $photo = Media::factory()->create(['type' => MediaType::IMAGE, 'source' => 'uploads/photo.jpg']);
    $video = Media::factory()->create(['type' => MediaType::VIDEO, 'source' => 'uploads/clip.mp4']);

    $record->media()->attach($photo->id, ['role' => 'photo', 'locale' => 'en', 'position' => 0, 'crop' => ['default' => ['crop_w' => 400, 'crop_h' => 300, 'crop_x' => 10, 'crop_y' => 20]]]);
    $record->media()->attach($video->id, ['role' => 'gallery', 'locale' => 'en', 'position' => 1]);

    $record->load('media');

    $images = schemaNodes($record, fallbackImage: 'https://cdn.example/fallback.jpg')['0']['image'];

    expect($images)->toHaveCount(1)
        ->and($images[0])->toContain('crop=400-300-10-20');
});

it('uses the fallback image when a record has no media', function (): void {
    $record = schemaRecord('service', [], ['title' => ['en' => 'Consulting']]);

    expect(schemaNodes($record, fallbackImage: 'https://cdn.example/share.jpg')[0]['image'])
        ->toBe(['https://cdn.example/share.jpg']);
});

it('omits the image when there is neither media nor a fallback', function (): void {
    $record = schemaRecord('service', [], ['title' => ['en' => 'Consulting']]);

    expect(schemaNodes($record)[0])->not->toHaveKey('image');
});

it('omits the description when none is provided', function (): void {
    $record = schemaRecord('service', [], ['title' => ['en' => 'Consulting']]);

    expect(schemaNodes($record, description: '')[0])->not->toHaveKey('description');
});

it('skips invalid event dates and empty locations', function (): void {
    $record = schemaRecord('event', ['starts_at' => 'not-a-date'], ['title' => ['en' => 'Mystery']]);

    $node = schemaNodes($record)[0];

    expect($node['@type'])->toBe('Event')
        ->and($node)->not->toHaveKeys(['startDate', 'endDate', 'location']);
});
