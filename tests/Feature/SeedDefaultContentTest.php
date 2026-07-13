<?php

declare(strict_types=1);

use App\Models\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function reseedDefaultContent(): void
{
    foreach (['blocks', 'translations', 'slugs', 'pages'] as $table) {
        DB::table($table)->delete();
    }
    DB::table('settings')->where('key', 'home_page_id')->delete();

    $migration = require database_path('migrations/2026_07_05_000000_seed_default_content.php');
    $migration->up();
}

it('seeds the default content under the configured default locale', function (): void {
    config()->set('app.locale', 'de');
    config()->set('app.default_locale', 'de');

    reseedDefaultContent();

    $welcome = Page::query()->whereHas('slugs', fn ($query) => $query->where('slug', 'welcome'))->firstOrFail();

    expect($welcome->metadata['published_locales'])->toBe(['de'])
        ->and($welcome->slugs()->where('slug', 'welcome')->value('locale'))->toBe('de')
        ->and(DB::table('translations')->where(['translatable_id' => $welcome->id, 'key' => 'title'])->value('locale'))->toBe('de')
        ->and($welcome->blocks()->where('type', 'rich-text')->orderBy('position')->firstOrFail()->content['body'])->toHaveKey('de');
});

it('marks the configured default locale active when seeding locales', function (): void {
    config()->set('app.locale', 'ro');

    Schema::drop('locales');
    $migration = require database_path('migrations/2025_11_10_025724_create_locales_table.php');
    $migration->up();

    expect(DB::table('locales')->where('active', true)->pluck('code')->all())->toBe(['ro']);
});

it('falls back to english when the configured locale is not in the catalog', function (): void {
    config()->set('app.locale', 'xx');

    Schema::drop('locales');
    $migration = require database_path('migrations/2025_11_10_025724_create_locales_table.php');
    $migration->up();

    expect(DB::table('locales')->where('active', true)->pluck('code')->all())->toBe(['en']);
});
