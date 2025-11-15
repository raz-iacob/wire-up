<?php

declare(strict_types=1);

use App\Models\Locale;
use App\Models\Page;
use App\Models\Slug;
use App\Models\User;
use Illuminate\Database\QueryException;

test('to array', function (): void {
    $slug = Slug::factory()->create()->refresh();

    expect(array_keys($slug->toArray()))
        ->toBe([
            'id',
            'slug',
            'locale',
            'sluggable_type',
            'sluggable_id',
            'created_at',
            'updated_at',
        ]);
});

it('has morphTo relationship with sluggable', function (): void {
    $page = Page::factory()->create();
    $slug = Slug::factory()->create([
        'sluggable_id' => $page->id,
        'sluggable_type' => Page::class,
    ]);

    expect($slug->sluggable)->toBeInstanceOf(Page::class)
        ->and($slug->sluggable->id)->toBe($page->id);
});

it('can be created for different model types', function (): void {
    $page = Page::factory()->create();
    $user = User::factory()->create();

    $pageSlug = Slug::factory()->create([
        'sluggable_id' => $page->id,
        'sluggable_type' => Page::class,
    ]);
    $userSlug = Slug::factory()->create([
        'sluggable_id' => $user->id,
        'sluggable_type' => User::class,
    ]);

    expect($pageSlug->sluggable_type)->toBe(Page::class)
        ->and($pageSlug->sluggable_id)->toBe($page->id)
        ->and($userSlug->sluggable_type)->toBe(User::class)
        ->and($userSlug->sluggable_id)->toBe($user->id);
});

it('enforces unique constraint on slug and locale combination', function (): void {
    $locale = Locale::query()->first();
    Slug::factory()->create([
        'slug' => 'unique-slug',
        'locale' => $locale->code,
    ]);

    expect(fn () => Slug::factory()->create([
        'slug' => 'unique-slug',
        'locale' => $locale->code,
    ]))->toThrow(QueryException::class);
});

it('allows same slug for different locales', function (): void {
    $locale1 = Locale::query()->first();
    $locale2 = Locale::query()->skip(1)->first();

    $slug1 = Slug::factory()->create([
        'slug' => 'same-slug',
        'locale' => $locale1->code,
    ]);

    $slug2 = Slug::factory()->create([
        'slug' => 'same-slug',
        'locale' => $locale2->code,
    ]);

    expect($slug1->slug)->toBe($slug2->slug)
        ->and($slug1->locale)->not->toBe($slug2->locale);
});

it('allows same locale for different slugs', function (): void {
    $locale = Locale::query()->first();

    $slug1 = Slug::factory()->create([
        'slug' => 'first-slug',
        'locale' => $locale->code,
    ]);

    $slug2 = Slug::factory()->create([
        'slug' => 'second-slug',
        'locale' => $locale->code,
    ]);

    expect($slug1->locale)->toBe($slug2->locale)
        ->and($slug1->slug)->not->toBe($slug2->slug);
});

it('belongs to locale through foreign key', function (): void {
    $locale = Locale::query()->first();
    $slug = Slug::factory()->create([
        'locale' => $locale->code,
    ]);

    expect($slug->locale)->toBe($locale->code);

    expect(fn () => Slug::factory()->create([
        'locale' => 'xx-nonexistent',
    ]))->toThrow(QueryException::class);
});
