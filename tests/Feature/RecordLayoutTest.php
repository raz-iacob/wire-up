<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Models\Media;
use App\Models\Record;
use App\Models\RecordType;

function publishRecordWithLayout(string $slug, array $layout): Record
{
    $type = RecordType::factory()->create(['key' => 'product', 'slug_prefix' => 'store']);

    $record = Record::factory()->for($type)->create([
        'metadata' => ['published_locales' => ['en'], 'layout' => $layout],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'title' => ucfirst($slug),
    ]);
    $record->slugs()->create(['locale' => 'en', 'slug' => $slug, 'base_path' => 'store']);

    return $record;
}

it('resolves an empty layout when the record has none', function (): void {
    $record = publishRecordWithLayout('plain', []);

    expect($record->resolvedLayout())->toMatchArray([
        'hideHeader' => false,
        'hideFooter' => false,
        'backgroundColor' => null,
        'backgroundImage' => null,
        'customCss' => '',
    ]);
});

it('shows the site chrome on a record page by default', function (): void {
    publishRecordWithLayout('with-chrome', []);

    $this->get(route('record', ['recordType' => 'store', 'slug' => 'with-chrome']))
        ->assertOk()
        ->assertSee('<header', false)
        ->assertSee('<footer', false);
});

it('hides the header and footer on a record page when configured', function (): void {
    publishRecordWithLayout('no-chrome', ['hideHeader' => true, 'hideFooter' => true]);

    $this->get(route('record', ['recordType' => 'store', 'slug' => 'no-chrome']))
        ->assertOk()
        ->assertDontSee('<header', false)
        ->assertDontSee('<footer', false);
});

it('applies a background colour to a record page', function (): void {
    publishRecordWithLayout('tinted', ['backgroundColor' => '#123456']);

    $this->get(route('record', ['recordType' => 'store', 'slug' => 'tinted']))
        ->assertOk()
        ->assertSee('background-color:#123456', false);
});

it('applies a background image to a record page', function (): void {
    $media = Media::factory()->create();

    publishRecordWithLayout('with-bg', [
        'backgroundImage' => ['source' => $media->source],
        'backgroundFixed' => true,
    ]);

    $this->get(route('record', ['recordType' => 'store', 'slug' => 'with-bg']))
        ->assertOk()
        ->assertSee('background-image:url(', false)
        ->assertSee('background-attachment:fixed', false);
});

it('injects a record page custom css and neutralises style-tag breakout', function (): void {
    publishRecordWithLayout('with-css', ['customCss' => '.x { color: red; } </style><script>alert(1)</script>']);

    $response = $this->get(route('record', ['recordType' => 'store', 'slug' => 'with-css']))->assertOk();

    $response->assertSee('.x { color: red; }', false)
        ->assertDontSee('</style><script>', false);
});
