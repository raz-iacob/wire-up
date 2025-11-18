<?php

declare(strict_types=1);

use App\Models\Locale;
use App\Models\Page;
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

it('redirects authenticated non-admin users away from pages index', function (): void {
    $user = User::factory()->create([
        'active' => true,
        'admin' => false,
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

    $response->assertRedirect(route('admin.pages-edit', ['page' => 2]));
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

it('shows translations column when multiple locales are configured', function (): void {
    Locale::query()->whereIn('code', ['en', 'de', 'fr'])->update([
        'active' => true,
    ]);

    $this->actingAsAdmin();

    $response = Livewire::test('pages::admin.pages-index');

    $response->assertSee(__('Translations'));
});
