<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Models\Category;
use App\Models\Locale;
use App\Models\Page;
use App\Models\Role;
use App\Models\Settings;
use App\Models\User;
use Livewire\Livewire;

it('can render the pages index screen', function (): void {

    Page::factory()->create([
        'title' => 'Sample Page',
    ]);

    $response = $this->actingAsAdmin()
        ->fromRoute('admin.dashboard')
        ->get(route('admin.pages-index'));

    $response->assertOk()
        ->assertSeeLivewire('pages::admin.pages-index');
});

it('duplicates a page via the modal with an editable title', function (): void {
    $page = Page::factory()->create(['title' => 'About']);

    $this->actingAsAdmin();

    $before = Page::query()->count();

    Livewire::test('pages::admin.pages-index')
        ->assertSee('Duplicate')
        ->call('duplicate', $page->id)
        ->assertSet('duplicateTitle', 'Copy of About')
        ->set('duplicateTitle', 'About Copy')
        ->call('confirmDuplicate')
        ->assertRedirect();

    expect(Page::query()->count())->toBe($before + 1)
        ->and(Page::query()->whereTranslationLike('title', 'About Copy')->exists())->toBeTrue();
});

it('redirects authenticated non-admin users away from pages index', function (): void {
    $user = User::factory()->create([
        'active' => true,
        'role' => 'member',
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('home')
        ->get(route('admin.pages-index'));

    $response->assertRedirectToRoute('home');
});

it('redirects guests away from pages index', function (): void {
    $response = $this->fromRoute('home')
        ->get(route('admin.pages-index'));

    $response->assertRedirectToRoute('login');
});

it('displays pages in the table', function (): void {
    $pages = Page::factory()->count(3)->create();

    $this->actingAsAdmin();

    $response = Livewire::test('pages::admin.pages-index');

    foreach ($pages as $page) {
        $response->assertSee($page->title)
            ->assertSee($page->status->label());
    }
});

it('can search page by title', function (): void {
    Page::factory()->createMany([
        ['title' => 'Homepage'],
        ['title' => 'About Us'],
        ['title' => 'Contact'],
    ]);

    $this->actingAsAdmin();

    $response = Livewire::test('pages::admin.pages-index')
        ->set('search', 'Homepage')
        ->call('$refresh');

    $response->assertSee('Homepage')
        ->assertDontSee('About Us');
});

it('can filter pages by status', function (): void {
    Page::factory()->createMany([
        ['title' => 'Draft Page', 'status' => 'draft'],
        ['title' => 'Published Page', 'status' => 'published'],
    ]);

    $this->actingAsAdmin();

    $response = Livewire::test('pages::admin.pages-index')
        ->set('status', 'draft')
        ->call('$refresh');

    $response->assertSee('Draft Page')
        ->assertDontSee('Published Page');

    $response = Livewire::test('pages::admin.pages-index')
        ->set('status', 'published')
        ->call('$refresh');

    $response->assertSee('Published Page')
        ->assertDontSee('Draft Page');
});

it('can sort pages by title', function (): void {
    Page::factory()->createMany([
        ['title' => 'First Page'],
        ['title' => 'Second Page'],
    ]);

    $this->actingAsAdmin();

    $response = Livewire::test('pages::admin.pages-index')
        ->call('sort', 'title');

    $response->assertSet('sortBy', 'title')
        ->assertSet('sortDirection', 'asc')
        ->assertSeeInOrder(['First Page', 'Second Page']);

    $response->call('sort', 'title');

    $response->assertSet('sortBy', 'title')
        ->assertSet('sortDirection', 'desc')
        ->assertSeeInOrder(['Second Page', 'First Page']);
});

it('can sort pages by updated_at', function (): void {
    Page::factory()->createMany([
        ['title' => 'First Page', 'updated_at' => now()->subDay()],
        ['title' => 'Second Page', 'updated_at' => now()],
    ]);

    $this->actingAsAdmin();

    $response = Livewire::test('pages::admin.pages-index')
        ->call('sort', 'updated_at');

    $response->assertSet('sortBy', 'updated_at')
        ->assertSet('sortDirection', 'asc')
        ->assertSeeInOrder(['First Page', 'Second Page']);

    $response->call('sort', 'updated_at');

    $response->assertSet('sortBy', 'updated_at')
        ->assertSet('sortDirection', 'desc')
        ->assertSeeInOrder(['Second Page', 'First Page']);
});

it('can add new a page', function (): void {
    $this->actingAsAdmin();

    $response = Livewire::test('pages::admin.pages-index')
        ->set('title', 'New Page')
        ->call('create');

    $response->assertHasNoErrors();

    $this->assertDatabaseHas('pages', [
        'status' => 'draft',
    ]);

    $response->assertRedirect(route('admin.pages-edit', ['page' => Page::query()->latest('id')->firstOrFail()->id]));
});

it('validates title when adding new page', function (): void {
    $this->actingAsAdmin();

    $response = Livewire::test('pages::admin.pages-index')
        ->set('title', '')
        ->call('create');

    $response->assertHasErrors(['title']);

    $response = Livewire::test('pages::admin.pages-index')
        ->set('title', str_repeat('a', 256))
        ->call('create');

    $response->assertHasErrors(['title']);
});

it('hides translations column when only one locale is active', function (): void {
    $this->actingAsAdmin();

    $response = Livewire::test('pages::admin.pages-index');

    $response->assertDontSee(__('Translations'));
});

it('shows languages column when multiple locales are configured', function (): void {
    Locale::query()->whereIn('code', ['en', 'de', 'fr'])->update([
        'active' => true,
    ]);

    $this->actingAsAdmin();

    $response = Livewire::test('pages::admin.pages-index');

    $response->assertSee(__('Languages'));
});

it('lists the active locales as badges in the languages column', function (): void {
    Locale::query()->whereIn('code', ['en', 'de', 'fr'])->update([
        'active' => true,
    ]);

    Page::factory()->create([
        'title' => 'Localized Page',
        'metadata' => ['published_locales' => ['en']],
    ]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.pages-index')
        ->assertSee('English')
        ->assertSee('German')
        ->assertSee('French');
});

it('flags the homepage in the pages list', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.pages-index')
        ->assertSee('Homepage');
});

it('returns to the first page when the search or status changes', function (): void {
    Page::factory()->count(25)->create();

    $this->actingAsAdmin();

    Livewire::test('pages::admin.pages-index')
        ->call('gotoPage', 2)
        ->assertSet('paginators.page', 2)
        ->set('search', 'Page')
        ->assertSet('paginators.page', 1)
        ->call('gotoPage', 2)
        ->set('status', 'draft')
        ->assertSet('paginators.page', 1);
});

it('refuses to delete the homepage and offers no delete for it', function (): void {
    $home = Page::factory()->create(['title' => 'Home Sweet Home']);
    Settings::set(['home_page_id' => $home->id]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.pages-index')
        ->assertDontSeeHtml('wire:click="confirmDelete('.$home->id.')"')
        ->call('delete', $home->id)
        ->assertDispatched('toast-show');

    expect(Page::query()->whereKey($home->id)->exists())->toBeTrue();
});

it('publishes the selected pages and counts only the ones that changed', function (): void {
    $alpha = Page::factory()->create(['title' => 'Alpha']);
    $beta = Page::factory()->create(['title' => 'Beta']);
    $livePublishedAt = now()->subWeek()->startOfSecond();
    $live = Page::factory()->create(['status' => ContentStatus::PUBLISHED, 'published_at' => $livePublishedAt]);
    $untouched = Page::factory()->create();

    $this->actingAsAdmin();

    Livewire::test('pages::admin.pages-index')
        ->set('selected', [(string) $alpha->id, (string) $beta->id, (string) $live->id])
        ->call('publishSelected')
        ->assertDispatched('toast-show', toastSays('2 pages published.'))
        ->assertSet('selected', []);

    expect($alpha->fresh()->status)->toBe(ContentStatus::PUBLISHED)
        ->and($beta->fresh()->status)->toBe(ContentStatus::PUBLISHED)
        ->and($live->fresh()->published_at?->equalTo($livePublishedAt))->toBeTrue()
        ->and($untouched->fresh()->status)->toBe(ContentStatus::DRAFT);
});

it('unpublishes the selected pages, scheduled ones included', function (): void {
    $live = Page::factory()->create(['status' => ContentStatus::PUBLISHED, 'published_at' => now()->subDay()]);
    $scheduled = Page::factory()->create(['status' => ContentStatus::PUBLISHED, 'published_at' => now()->addDay()]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.pages-index')
        ->set('selected', [(string) $live->id, (string) $scheduled->id])
        ->call('unpublishSelected')
        ->assertDispatched('toast-show', toastSays('2 pages unpublished.'));

    expect($live->fresh()->status)->toBe(ContentStatus::DRAFT)
        ->and($scheduled->fresh()->status)->toBe(ContentStatus::DRAFT)
        ->and($scheduled->fresh()->published_at)->toBeNull();
});

it('adds the selected pages to a category without touching their status', function (): void {
    $live = Page::factory()->create(['status' => ContentStatus::PUBLISHED, 'published_at' => now()->subDay()]);
    $draft = Page::factory()->create();
    $news = Category::factory()->create(['name' => ['en' => 'News']]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.pages-index')
        ->set('selected', [(string) $live->id, (string) $draft->id])
        ->set('bulkCategoryId', $news->id)
        ->call('addSelectedToCategory')
        ->assertHasNoErrors()
        ->assertDispatched('toast-show', toastSays('2 pages added to News.'))
        ->assertSet('bulkCategoryId', null);

    expect($live->fresh()->categories->modelKeys())->toBe([$news->id])
        ->and($live->fresh()->status)->toBe(ContentStatus::PUBLISHED)
        ->and($draft->fresh()->categories->modelKeys())->toBe([$news->id]);
});

it('asks which category before adding pages to one', function (): void {
    $page = Page::factory()->create();
    Category::factory()->create();

    $this->actingAsAdmin();

    Livewire::test('pages::admin.pages-index')
        ->set('selected', [(string) $page->id])
        ->call('addSelectedToCategory')
        ->assertHasErrors(['bulkCategoryId' => 'required'])
        ->assertSee('The category field is required.');

    expect($page->categories()->exists())->toBeFalse();
});

it('deletes the selected pages but keeps the homepage', function (): void {
    $home = Page::factory()->create(['title' => 'Home']);
    Settings::set(['home_page_id' => $home->id]);
    $alpha = Page::factory()->create();
    $beta = Page::factory()->create();

    $this->actingAsAdmin();

    Livewire::test('pages::admin.pages-index')
        ->set('selected', [(string) $home->id, (string) $alpha->id, (string) $beta->id])
        ->call('deleteSelected')
        ->assertDispatched('toast-show', toastSays('2 pages deleted. The homepage was kept. Choose another homepage in Settings → General to delete it.'));

    expect(Page::query()->whereKey($home->id)->exists())->toBeTrue()
        ->and(Page::query()->whereKey([$alpha->id, $beta->id])->exists())->toBeFalse();
});

it('keeps bulk changes from staff who may only view pages', function (): void {
    $page = Page::factory()->create();
    $viewer = User::factory()->role(Role::factory()->create(['abilities' => ['pages.view'], 'bypass' => false]))->create(['active' => true]);

    $this->actingAs($viewer);

    Livewire::test('pages::admin.pages-index')
        ->assertDontSeeHtml('aria-label="Select all"')
        ->set('selected', [(string) $page->id])
        ->call('publishSelected')
        ->assertForbidden();

    Livewire::test('pages::admin.pages-index')
        ->set('selected', [(string) $page->id])
        ->call('deleteSelected')
        ->assertForbidden();

    expect($page->fresh()->status)->toBe(ContentStatus::DRAFT);
});

it('clears the selection when the search, the filter or the page changes', function (): void {
    $page = Page::factory()->create();

    $this->actingAsAdmin();

    Livewire::test('pages::admin.pages-index')
        ->set('selected', [(string) $page->id])
        ->set('search', 'something')
        ->assertSet('selected', [])
        ->set('selected', [(string) $page->id])
        ->set('status', ContentStatus::DRAFT->value)
        ->assertSet('selected', [])
        ->set('selected', [(string) $page->id])
        ->call('gotoPage', 2)
        ->assertSet('selected', []);
});
