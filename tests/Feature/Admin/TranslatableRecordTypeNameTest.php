<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Models\Locale;
use App\Models\Record;
use App\Models\RecordType;
use App\Models\Translation;
use App\Services\BreadcrumbService;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function (): void {
    Locale::query()->updateOrCreate(['code' => 'nl'], ['name' => 'Nederlands', 'active' => true, 'regional' => 'nl-NL']);

    $this->type = RecordType::factory()->create([
        'key' => 'guide',
        'name' => 'Guides',
        'slug_prefix' => 'guides',
        'fields' => [],
        'breadcrumbs' => true,
    ]);
});

it('reads the name for the current language, falling back to the stored one', function (): void {
    $this->type->syncNameTranslations(['nl' => 'Handleidingen']);

    $type = $this->type->fresh();

    expect($type->nameFor('en'))->toBe('Guides')
        ->and($type->nameFor('nl'))->toBe('Handleidingen');

    app()->setLocale('nl');

    expect($type->name)->toBe('Handleidingen');
});

it('keeps writing the stored name to its own column', function (): void {
    $this->type->syncNameTranslations(['nl' => 'Handleidingen']);
    $this->type->update(['name' => 'Manuals']);

    expect(DB::table('record_types')->where('id', $this->type->id)->value('name'))->toBe('Manuals')
        ->and($this->type->fresh()->nameFor('nl'))->toBe('Handleidingen')
        ->and($this->type->fresh()->nameFor('en'))->toBe('Manuals');
});

it('falls back when a translation is blanked out', function (): void {
    $this->type->syncNameTranslations(['nl' => 'Handleidingen']);
    $this->type->syncNameTranslations(['nl' => '  ']);

    expect($this->type->fresh()->nameFor('nl'))->toBe('Guides')
        ->and(Translation::query()->where('key', 'name')->count())->toBe(0);
});

it('uses the translated name in a public breadcrumb', function (): void {
    $this->type->syncNameTranslations(['nl' => 'Handleidingen']);

    $record = Record::factory()->create([
        'record_type_id' => $this->type->id,
        'title' => ['en' => 'Getting started', 'nl' => 'Aan de slag'],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'metadata' => ['published_locales' => ['en', 'nl']],
    ]);
    $record->setSlugs();

    app()->setLocale('nl');

    $labels = array_column(resolve(BreadcrumbService::class)->trail($record->fresh()), 'label');

    expect($labels)->toContain('Handleidingen')->not->toContain('Guides');
});

it('offers a name field for every language but the default one', function (): void {
    $this->actingAsAdmin();

    $component = Livewire::test('pages::admin.settings-content-types');

    expect($component->instance()->extraLocales())->toBe(['nl' => 'Nederlands']);

    $component->assertSee('Name in Nederlands');
});

it('saves a per-language name from the content types builder', function (): void {
    $this->actingAsAdmin();

    $component = Livewire::test('pages::admin.settings-content-types');
    $index = array_search('guide', array_column($component->get('types'), 'key'), true);

    $component->set("types.{$index}.translations.nl", 'Handleidingen')->call('update')->assertHasNoErrors();

    expect($this->type->fresh()->nameFor('nl'))->toBe('Handleidingen')
        ->and(DB::table('record_types')->where('id', $this->type->id)->value('name'))->toBe('Guides');
});

it('loads existing per-language names into the builder', function (): void {
    $this->type->syncNameTranslations(['nl' => 'Handleidingen']);

    $this->actingAsAdmin();

    $rows = Livewire::test('pages::admin.settings-content-types')->get('types');
    $row = collect($rows)->firstWhere('key', 'guide');

    expect($row['name'])->toBe('Guides')
        ->and($row['translations'])->toBe(['nl' => 'Handleidingen']);
});

it('drops the translations when the content type goes', function (): void {
    $this->type->syncNameTranslations(['nl' => 'Handleidingen']);

    expect(Translation::query()->where('key', 'name')->count())->toBe(1);

    $this->type->delete();

    expect(Translation::query()->where('key', 'name')->count())->toBe(0);
});
