<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use App\Models\Page;
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
        ->assertSeeLivewire('pages::admin.pages-edit');
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
