<?php

declare(strict_types=1);

use App\Enums\MediaType;
use App\Enums\PageStatus;
use App\Models\Media;
use App\Models\Page;
use App\Models\Settings;
use App\Models\User;
use Livewire\Livewire;

it('can render the pages edit screen', function (): void {
    $page = Page::factory()->create([
        'title' => 'Sample Page',
    ]);

    $response = $this->actingAsAdmin()
        ->fromRoute('admin.pages-index')
        ->get(route('admin.pages-edit', $page));

    $response->assertOk()
        ->assertSeeLivewire('pages::admin.pages-edit')
        ->assertSeeLivewire('admin.media-selector');
});

it('redirects authenticated non-admin users away from pages edit', function (): void {
    $nonAdmin = User::factory()->create([
        'active' => true,
        'admin' => false,
    ]);

    $page = Page::factory()->create();

    $response = $this->actingAs($nonAdmin)
        ->fromRoute('home')
        ->get(route('admin.pages-edit', $page));

    $response->assertRedirectToRoute('home');
});

it('redirects guests away from pages edit', function (): void {
    $page = Page::factory()->create();

    $response = $this->fromRoute('home')
        ->get(route('admin.pages-edit', $page));

    $response->assertRedirectToRoute('login');
});

it('populates form with page data on mount', function (): void {
    $page = Page::factory()->create([
        'status' => PageStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);

    $this->actingAsAdmin();

    $response = Livewire::test('pages::admin.pages-edit', ['page' => $page]);

    $response->assertSet('status', PageStatus::PUBLISHED)
        ->assertSet('published_at', $page->published_at)
        ->assertSet('page.id', $page->id);
});

it('displays page creation information', function (): void {
    $page = Page::factory()->create([
        'created_at' => now()->subWeeks(2),
    ]);

    $this->actingAsAdmin();

    $response = Livewire::test('pages::admin.pages-edit', ['page' => $page]);

    $response->assertSee($page->created_at->format('M d, Y H:i'));
});

it('can update page basic information', function (): void {
    $page = Page::factory()->create([
        'status' => PageStatus::DRAFT,
    ]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.pages-edit', ['page' => $page])
        ->set('status', PageStatus::PUBLISHED)
        ->set('title.en', 'Updated Title')
        ->set('slugs.en', 'updated-slug')
        ->call('update');

    $this->assertDatabaseHas('pages', [
        'id' => $page->id,
        'status' => PageStatus::PUBLISHED->value,
    ]);

    $this->assertDatabaseHas('translations', [
        'translatable_id' => $page->id,
        'translatable_type' => 'page',
        'locale' => 'en',
        'key' => 'title',
        'body' => 'Updated Title',
    ]);
});

it('validates required fields', function (): void {
    $page = Page::factory()->create();

    $this->actingAsAdmin();

    Livewire::test('pages::admin.pages-edit', ['page' => $page])
        ->set('title.en', '')
        ->call('update')
        ->assertHasErrors(['title.en' => 'required']);
});

it('validates slug uniqueness', function (): void {
    $existingPage = Page::factory()->create();
    $existingPage->updateSlugs(['en' => 'existing-slug']);

    $page = Page::factory()->create();

    $this->actingAsAdmin();

    Livewire::test('pages::admin.pages-edit', ['page' => $page])
        ->set('title.en', 'Test Title')
        ->set('slugs.en', 'existing-slug')
        ->call('update')
        ->assertHasErrors(['slugs.en']);
});

it('can change page status to published', function (): void {
    $page = Page::factory()->create([
        'status' => PageStatus::DRAFT,
    ]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.pages-edit', ['page' => $page])
        ->set('status', PageStatus::PUBLISHED)
        ->set('title.en', 'Published Page')
        ->set('slugs.en', 'published-page')
        ->call('update');

    $page->refresh();

    expect($page->status)->toBe(PageStatus::PUBLISHED)
        ->and($page->published_at)->not->toBeNull();
});

it('can change page status to private', function (): void {
    $page = Page::factory()->create([
        'status' => PageStatus::PUBLISHED,
        'published_at' => now(),
    ]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.pages-edit', ['page' => $page])
        ->set('status', PageStatus::PRIVATE)
        ->set('title.en', 'Private Page')
        ->set('slugs.en', 'private-page')
        ->call('update');

    $page->refresh();

    expect($page->status)->toBe(PageStatus::PRIVATE)
        ->and($page->published_at)->toBeNull();
});

it('initializes og_image with an empty array for each active locale when there is no media', function (): void {
    $page = Page::factory()->create();

    $this->actingAsAdmin();

    $og = Livewire::test('pages::admin.pages-edit', ['page' => $page])->get('og_image');

    expect($og)->toHaveKey('en')
        ->and($og['en'])->toBe([]);
});

it('hydrates og_image from existing media on mount', function (): void {
    $page = Page::factory()->create();
    $media = Media::factory()->create(['type' => MediaType::IMAGE]);
    $page->syncMediaForRole('og_image', 'en', [
        ['id' => $media->id, 'crop' => ['default' => ['crop_w' => 1200, 'crop_h' => 630, 'crop_x' => 0, 'crop_y' => 0]], 'metadata' => ['caption' => 'Hydrated caption']],
    ]);

    $this->actingAsAdmin();

    $og = Livewire::test('pages::admin.pages-edit', ['page' => $page])->get('og_image');

    expect($og)->toHaveKey('en')
        ->and($og['en'])->toHaveCount(1)
        ->and($og['en'][0]['id'])->toBe($media->id)
        ->and($og['en'][0]['crop'])->toBe(['default' => ['crop_w' => 1200, 'crop_h' => 630, 'crop_x' => 0, 'crop_y' => 0]])
        ->and($og['en'][0]['metadata'])->toBe(['caption' => 'Hydrated caption']);
});

it('persists og_image caption metadata on update', function (): void {
    $page = Page::factory()->create(['status' => PageStatus::DRAFT]);
    $media = Media::factory()->create(['type' => MediaType::IMAGE]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.pages-edit', ['page' => $page])
        ->set('title.en', 'With caption')
        ->set('slugs.en', 'with-caption')
        ->set('og_image.en', [
            ['id' => $media->id, 'metadata' => ['caption' => 'My caption', 'alt' => 'My alt']],
        ])
        ->call('update')
        ->assertHasNoErrors();

    $pivot = $page->fresh()->media()->wherePivot('role', 'og_image')->first()->pivot;

    expect($pivot->metadata)->toBe(['caption' => 'My caption', 'alt' => 'My alt']);
});

it('persists a single (non-list) og_image item from a single-select selector', function (): void {
    $page = Page::factory()->create(['status' => PageStatus::DRAFT]);
    $media = Media::factory()->create(['type' => MediaType::IMAGE]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.pages-edit', ['page' => $page])
        ->set('title.en', 'Single')
        ->set('slugs.en', 'single')
        ->set('og_image.en', ['id' => $media->id])
        ->call('update')
        ->assertHasNoErrors();

    expect($page->fresh()->media()->wherePivot('role', 'og_image')->count())->toBe(1);
});

it('clears og_image when the single selection is removed (null)', function (): void {
    $page = Page::factory()->create(['status' => PageStatus::DRAFT]);
    $media = Media::factory()->create(['type' => MediaType::IMAGE]);
    $page->syncMediaForRole('og_image', 'en', [['id' => $media->id]]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.pages-edit', ['page' => $page])
        ->set('title.en', 'Cleared')
        ->set('slugs.en', 'cleared')
        ->set('og_image.en')
        ->call('update')
        ->assertHasNoErrors();

    expect($page->fresh()->media()->wherePivot('role', 'og_image')->count())->toBe(0);
});

it('persists selected og_image media on update', function (): void {
    $page = Page::factory()->create(['status' => PageStatus::DRAFT]);
    $media = Media::factory()->create(['type' => MediaType::IMAGE]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.pages-edit', ['page' => $page])
        ->set('title.en', 'With OG')
        ->set('slugs.en', 'with-og')
        ->set('og_image.en', [
            ['id' => $media->id, 'crop' => ['default' => ['crop_w' => 1200, 'crop_h' => 630, 'crop_x' => 0, 'crop_y' => 0]]],
        ])
        ->call('update')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('mediables', [
        'mediable_id' => $page->id,
        'mediable_type' => $page->getMorphClass(),
        'media_id' => $media->id,
        'role' => 'og_image',
        'locale' => 'en',
        'position' => 0,
    ]);
});

it('rejects og_image referencing a missing media id', function (): void {
    $page = Page::factory()->create();

    $this->actingAsAdmin();

    Livewire::test('pages::admin.pages-edit', ['page' => $page])
        ->set('title.en', 'Bad OG')
        ->set('slugs.en', 'bad-og')
        ->set('og_image.en', [['id' => 999999]])
        ->call('update')
        ->assertHasErrors(['og_image.en.0.id']);
});

it('can schedule a page for future publication', function (): void {
    $futureDate = now()->addDays(7);

    $page = Page::factory()->create([
        'status' => PageStatus::DRAFT,
    ]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.pages-edit', ['page' => $page])
        ->set('status', PageStatus::SCHEDULED)
        ->set('published_at', $futureDate)
        ->set('title.en', 'Scheduled Page')
        ->set('slugs.en', 'scheduled-page')
        ->call('update');

    $page->refresh();

    expect($page->status)->toBe(PageStatus::PUBLISHED)
        ->and($page->published_at->timestamp)->toBe($futureDate->timestamp);
});

it('marks the page as the homepage in the editor', function (): void {
    $page = Page::factory()->create([
        'title' => 'Home Landing',
        'status' => PageStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);
    Settings::set(['home_page_id' => $page->id]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.pages-edit', ['page' => $page])
        ->assertSee('Homepage')
        ->assertSee('Served at');
});

it('does not mark a non-homepage page as the homepage', function (): void {
    $page = Page::factory()->create(['title' => 'Some Page']);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.pages-edit', ['page' => $page])
        ->assertDontSee('Homepage');
});
