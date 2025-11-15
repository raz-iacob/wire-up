<?php

declare(strict_types=1);

use App\Models\Locale;
use App\Models\Page;
use App\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;

it('has a morphMany relationship with page model', function (): void {
    $page = Page::factory()->create();
    expect($page->translations())->toBeInstanceOf(MorphMany::class);
});

it('has MorphOne relationship for current locale translation', function (): void {
    $page = Page::factory()->create();
    expect($page->translation())->toBeInstanceOf(MorphOne::class);
});

it('has title as default translated attribute', function (): void {
    Schema::create('posts', function ($table): void {
        $table->id();
        $table->string('type');
        $table->timestamps();
    });

    $model = new class extends Model
    {
        use HasFactory, HasTranslations;

        protected $table = 'posts';

        protected static function boot(): void
        {
            parent::boot();
            self::bootHasTranslations();
        }

        protected static function booted(): void
        {
            Relation::morphMap([
                'posts' => self::class,
            ]);
        }
    };

    $post = $model->create([
        'type' => 'example',
        'title' => 'Test',
    ]);

    expect($post->title)->toBe('Test');
});

it('saves translations on create for each active locale', function (): void {
    Locale::query()->whereIn('code', ['en', 'fr'])->update(['active' => true]);
    $page = Page::factory()->create([
        'title' => [
            'en' => 'Test Title',
            'fr' => 'Titre de test',
            'de' => 'Testtitel',
        ],
    ]);

    $translations = $page->translations()->get();

    expect($translations)->toHaveCount(6)
        ->and($translations->where('locale', 'en')->first()->body)->toBe('Test Title')
        ->and($translations->where('locale', 'fr')->first()->body)->toBe('Titre de test');
});

it('updates translations correctly for each active locale', function (): void {
    Locale::query()->whereIn('code', ['en', 'fr'])->update(['active' => true]);

    $page = Page::factory()->create([
        'title' => [
            'en' => 'Initial Title',
            'fr' => 'Titre Initial',
            'de' => 'Anfänglicher Titel',
        ],
    ]);

    $page->update([
        'title' => [
            'en' => 'Updated Title',
            'fr' => 'Titre Mis à Jour',
        ],
    ]);

    $translations = $page->translations()->get();

    expect($translations)->toHaveCount(6)
        ->and($translations->where('locale', 'en')->first()->body)->toBe('Updated Title')
        ->and($translations->where('locale', 'fr')->first()->body)->toBe('Titre Mis à Jour');
});

it('deletes translations on model deletion', function (): void {
    Locale::query()->whereIn('code', ['en', 'fr'])->update(['active' => true]);

    $page = Page::factory()->create([
        'title' => [
            'en' => 'Title to be deleted',
            'fr' => 'Titre à supprimer',
        ],
    ]);

    $this->assertDatabaseCount('translations', 6);

    $page->delete();

    $this->assertDatabaseCount('translations', 0);
});

it('gets translated attribute for current locale', function (): void {
    Locale::query()->whereIn('code', ['en', 'fr'])->update(['active' => true]);

    $page = Page::factory()->create([
        'title' => [
            'en' => 'English Title',
            'fr' => 'Titre Français',
        ],
    ]);

    app()->setLocale('fr');
    expect($page->title)->toBe('Titre Français');

    app()->setLocale('en');
    expect($page->title)->toBe('English Title');
});

it('falls back to any available translation if current locale translation is missing', function (): void {
    Locale::query()->whereIn('code', ['en', 'fr'])->update(['active' => true]);

    $page = Page::factory()->create([
        'title' => [
            'en' => 'English Title',
        ],
    ]);

    app()->setLocale('fr');
    expect($page->title)->toBe('English Title');
});

it('returns empty string if no translations are available', function (): void {
    $page = Page::factory()->create();
    expect($page->title)->toBe('');
});

it('includes translations array to model array if relation is loaded', function (): void {
    Locale::query()->whereIn('code', ['en', 'fr'])->update(['active' => true]);

    $page = Page::factory()->create([
        'name' => 'Test Page',
        'title' => [
            'en' => 'English Title',
            'fr' => 'Titre Français',
        ],
    ]);

    $page->load('translations');

    $attributes = $page->toArray();

    expect($attributes['title'])->toBe([
        'en' => 'English Title',
        'fr' => 'Titre Français',
    ])
        ->and($attributes)->toHaveKey('translations');
});

it('does not include translations array to model array if relation is not loaded', function (): void {
    Locale::query()->whereIn('code', ['en', 'fr'])->update(['active' => true]);

    $page = Page::factory()->create([
        'name' => 'Test Page',
        'title' => [
            'en' => 'English Title',
            'fr' => 'Titre Français',
        ],
    ]);

    $attributes = $page->toArray();

    expect($attributes)->not->toHaveKey('title')
        ->and($attributes)->not->toHaveKey('translations');
});
