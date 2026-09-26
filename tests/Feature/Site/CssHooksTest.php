<?php

declare(strict_types=1);

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use App\Models\Settings as SettingsModel;
use App\Services\SiteExporter;

it('gives each kind of card its own class alongside the shared one', function (): void {
    $type = RecordType::factory()->create(['key' => 'post', 'slug_prefix' => 'posts', 'fields' => []]);

    $record = Record::factory()->create([
        'record_type_id' => $type->id,
        'title' => ['en' => 'A post'],
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);
    $record->setSlugs();

    $page = Page::factory()->create([
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'metadata' => ['published_locales' => ['en']],
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'hooks']);

    $page->blocks()->create([
        'type' => BlockType::COLLECTION,
        'position' => 0,
        'content' => ['recordTypeId' => $type->id, 'source' => 'latest', 'layout' => 'grid'],
    ]);
    $page->blocks()->create([
        'type' => BlockType::FEATURE_CARDS,
        'position' => 1,
        'content' => ['items' => [['title' => ['en' => 'A feature']]]],
    ]);
    $page->blocks()->create([
        'type' => BlockType::TESTIMONIALS,
        'position' => 2,
        'content' => ['layout' => 'grid', 'items' => [['quote' => ['en' => 'Great'], 'author' => ['en' => 'Sam']]]],
    ]);
    $page->blocks()->create([
        'type' => BlockType::STATS,
        'position' => 3,
        'content' => ['layout' => 'cards', 'items' => [['value' => ['en' => '10'], 'label' => ['en' => 'Years']]]],
    ]);

    $html = (string) test()->get('/hooks')->assertOk()->getContent();

    expect($html)->toContain('wire-card wire-card--collection')
        ->toContain('wire-card wire-card--feature')
        ->toContain('wire-card wire-card--testimonial')
        ->toContain('wire-card wire-card--stat');
});

it('keeps emitting the block and record hooks custom CSS targets', function (): void {
    $page = Page::factory()->create([
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'metadata' => ['published_locales' => ['en']],
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'hooked']);
    $page->blocks()->create([
        'type' => BlockType::RICH_TEXT,
        'position' => 0,
        'content' => ['anchor' => 'intro', 'heading' => ['en' => '<p>Hi</p>'], 'body' => ['en' => '<p>Copy</p>']],
    ]);

    $html = (string) test()->get('/hooked')->assertOk()->getContent();

    expect($html)->toContain('data-block="rich-text"')
        ->toContain('id="intro"')
        ->toContain('--wire-heading-size')
        ->toContain('wire-prose');
});

it('names an export bundle after the site', function (): void {
    SettingsModel::set(['title' => ['en' => 'Trifecta Healing & Wellness']]);

    expect(SiteExporter::bundleName('2026-09-01-200659'))
        ->toBe('trifecta-healing-wellness-2026-09-01-200659.zip');
});

it('falls back to a generic bundle name when the title cannot be slugified', function (): void {
    SettingsModel::set(['title' => ['en' => '日本語']]);

    expect(SiteExporter::bundleName('2026-09-01-200659'))->toBe('site-2026-09-01-200659.zip');
});
