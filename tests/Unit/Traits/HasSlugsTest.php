<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Models\Locale;
use App\Models\Page;
use App\Traits\HasSlugs;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;

it('has a morphMany relationship with page model', function (): void {
    $page = Page::factory()->create();
    expect($page->slugs())->toBeInstanceOf(MorphMany::class);
});

it('has slug attribute accessor', function (): void {
    $page = Page::factory()->create([
        'title' => ['en' => 'Test Page'],
    ]);

    $page->setSlugs();

    expect($page->slug)->toBe('test-page');
});

it('generates slugs for all active locales on save', function (): void {
    Locale::query()->whereIn('code', ['en', 'es'])->update(['active' => true]);

    $page = Page::factory()->create([
        'title' => [
            'en' => 'Sample Post',
            'fr' => 'Article Exemple',
            'es' => 'Publicación de muestra',
        ],
    ])->refresh();

    $page->setSlugs();

    expect($page->slugs)->toHaveCount(2);

    $enSlug = $page->slugs->where('locale', 'en')->first();
    $esSlug = $page->slugs->where('locale', 'es')->first();
    $frSlug = $page->slugs->where('locale', 'fr')->first();

    expect($enSlug->slug)->toBe('sample-post')
        ->and($esSlug->slug)->toBe('publicacion-de-muestra')
        ->and($frSlug)->toBeNull();
});

it('generates slugs from multiple attributes', function (): void {

    Schema::create('posts', function ($table): void {
        $table->id();
        $table->string('title');
        $table->string('subtitle');
        $table->timestamps();
    });

    $model = new class extends Model
    {
        use HasFactory, HasSlugs;

        protected $table = 'posts';

        protected static function boot(): void
        {
            parent::boot();
            self::bootHasSlugs();
        }

        protected static function booted(): void
        {
            Relation::morphMap([
                'posts' => self::class,
            ]);
        }

        protected function slugAttributes(): array
        {
            return ['title', 'subtitle'];
        }
    };

    $post = $model->create([
        'title' => 'Test',
        'subtitle' => 'Page',
    ]);

    $post->setSlugs();

    expect($post->slug)->toBe('test-page');
});

it('generates slugs from enum cased attributes', function (): void {

    Schema::create('posts', function ($table): void {
        $table->id();
        $table->string('title');
        $table->string('status');
        $table->timestamps();
    });

    $model = new class extends Model
    {
        use HasFactory, HasSlugs;

        protected $table = 'posts';

        protected $casts = [
            'status' => PageStatus::class,
        ];

        protected static function boot(): void
        {
            parent::boot();
            self::bootHasSlugs();
        }

        protected static function booted(): void
        {
            Relation::morphMap([
                'posts' => self::class,
            ]);
        }

        protected function slugAttributes(): array
        {
            return ['title', 'status'];
        }
    };

    $post = $model->create([
        'title' => 'Test',
        'status' => PageStatus::PUBLISHED,
    ]);

    $post->setSlugs();

    expect($post->slug)->toBe('test-published');
});

it('resolves slug conflicts by appending counter', function (): void {
    $page1 = Page::factory()->create(['title' => ['en' => 'Test Page']]);
    $page1->setSlugs();
    expect($page1->slug)->toBe('test-page');

    $page2 = Page::factory()->create(['title' => ['en' => 'Test Page']]);
    $page2->setSlugs();
    expect($page2->slug)->toBe('test-page-1');
    $page3 = Page::factory()->create(['title' => ['en' => 'Test Page']]);
    $page3->setSlugs();
    expect($page3->slug)->toBe('test-page-2');
});

it('returns slug attribute accessor in current locale', function (): void {
    Locale::query()->whereIn('code', ['en', 'fr'])->update(['active' => true]);

    $page = Page::factory()->create(['title' => [
        'en' => 'Test Page',
        'fr' => 'Page de test',
    ]]);

    $page->setSlugs();

    app()->setLocale('en');
    expect($page->slug)->toBe('test-page');

    app()->setLocale('fr');
    expect($page->slug)->toBe('page-de-test');

    app()->setLocale('en');
});

it('returns correct slug for specific locale', function (): void {
    Locale::query()->whereIn('code', ['en', 'fr'])->update(['active' => true]);

    $page = Page::factory()->create(['title' => [
        'en' => 'Test Page',
        'fr' => 'Page de test',
    ]]);

    $page->setSlugs();

    expect($page->getSlug('en'))->toBe('test-page')
        ->and($page->getSlug('fr'))->toBe('page-de-test');
});

it('returns slugs array', function (): void {
    Locale::query()->whereIn('code', ['en', 'fr'])->update(['active' => true]);

    $page = Page::factory()->create(['title' => [
        'en' => 'Test Page',
        'fr' => 'Page de test',
    ]]);

    $page->setSlugs();

    $slugsArray = $page->getSlugsArray();

    expect($slugsArray)->toBe([
        'en' => 'test-page',
        'fr' => 'page-de-test',
    ]);
});

it('queries by slug with forSlug scope', function (): void {
    Locale::query()->whereIn('code', ['en', 'fr'])->update(['active' => true]);

    $page1 = Page::factory()->create(['title' => [
        'en' => 'Test Slug1',
        'fr' => 'Test Slug2',
    ]]);

    $page1->setSlugs();

    $page2 = Page::factory()->create(['title' => [
        'en' => 'Test Slug2',
        'fr' => 'Test Slug1',
    ]]);

    $page2->setSlugs();

    $testPage = Page::query()->forSlug('test-slug2')->first();
    $testPageFr = Page::query()->forSlug('test-slug1', 'fr')->first();

    expect($testPage->id)->toBe($page2->id)
        ->and($testPageFr->id)->toBe($page2->id);
});

it('throws exception when slug attribute does not exist', function (): void {
    Schema::create('posts', function ($table): void {
        $table->id();
        $table->string('title');
        $table->string('subtitle');
        $table->timestamps();
    });

    $model = new class extends Model
    {
        use HasFactory, HasSlugs;

        protected $table = 'posts';

        protected static function boot(): void
        {
            parent::boot();
            self::bootHasSlugs();
        }

        protected static function booted(): void
        {
            Relation::morphMap([
                'posts' => self::class,
            ]);
        }

        protected function slugAttributes(): array
        {
            return ['nonexistent_field'];
        }
    };

    $post = $model->create([
        'title' => 'Test',
        'subtitle' => 'Page',
    ]);

    expect(fn () => $post->setSlugs())
        ->toThrow(InvalidArgumentException::class, 'You must define the field nonexistent_field in your model');
});

it('can update slugs', function (): void {
    $page = Page::factory()->create(['title' => ['en' => 'Original Title']]);
    $page->setSlugs();

    $this->assertDatabaseHas('slugs', [
        'sluggable_type' => $page->getMorphClass(),
        'sluggable_id' => $page->id,
        'locale' => 'en',
        'slug' => 'original-title',
    ]);

    $page->updateSlugs([
        'en' => 'updated-title',
    ]);

    $this->assertDatabaseHas('slugs', [
        'sluggable_type' => $page->getMorphClass(),
        'sluggable_id' => $page->id,
        'locale' => 'en',
        'slug' => 'updated-title',
    ]);

    $this->assertDatabaseMissing('slugs', [
        'sluggable_type' => $page->getMorphClass(),
        'sluggable_id' => $page->id,
        'locale' => 'en',
        'slug' => 'original-title',
    ]);
});

it('deletes slugs on model deletion', function (): void {
    $page = Page::factory()->create(['title' => ['en' => 'Deletable Page']]);
    $page->setSlugs();

    $this->assertDatabaseHas('slugs', [
        'sluggable_type' => $page->getMorphClass(),
        'sluggable_id' => $page->id,
        'locale' => 'en',
        'slug' => 'deletable-page',
    ]);

    $page->delete();

    $this->assertDatabaseMissing('slugs', [
        'sluggable_type' => $page->getMorphClass(),
        'sluggable_id' => $page->id,
        'locale' => 'en',
        'slug' => 'deletable-page',
    ]);
});
