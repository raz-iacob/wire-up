<?php

declare(strict_types=1);

use App\Actions\CreateCategoryAction;
use App\Models\Category;
use App\Models\User;
use Livewire\Livewire;

it('can render the categories screen', function (): void {
    $this->actingAsAdmin()
        ->fromRoute('admin.dashboard')
        ->get(route('admin.categories-index'))
        ->assertOk()
        ->assertSeeLivewire('pages::admin.categories-index');
});

it('redirects authenticated non-admin users away from categories', function (): void {
    $user = User::factory()->create(['active' => true, 'admin' => false]);

    $this->actingAs($user)
        ->fromRoute('home')
        ->get(route('admin.categories-index'))
        ->assertRedirectToRoute('home');
});

it('redirects guests away from categories', function (): void {
    $this->fromRoute('home')
        ->get(route('admin.categories-index'))
        ->assertRedirectToRoute('login');
});

it('lists categories', function (): void {
    resolve(CreateCategoryAction::class)->handle(['name' => ['en' => 'Featured']]);
    resolve(CreateCategoryAction::class)->handle(['name' => ['en' => 'Archived']]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.categories-index')
        ->assertSee('Featured')
        ->assertSee('Archived');
});

it('can search categories by name', function (): void {
    resolve(CreateCategoryAction::class)->handle(['name' => ['en' => 'Findable']]);
    resolve(CreateCategoryAction::class)->handle(['name' => ['en' => 'Hidden']]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.categories-index')
        ->set('search', 'Findable')
        ->assertSee('Findable')
        ->assertDontSee('Hidden');
});

it('can sort categories by name', function (): void {
    resolve(CreateCategoryAction::class)->handle(['name' => ['en' => 'Alpha']]);
    resolve(CreateCategoryAction::class)->handle(['name' => ['en' => 'Beta']]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.categories-index')
        ->call('sort', 'name')
        ->assertSet('sortDirection', 'asc')
        ->assertSeeInOrder(['Alpha', 'Beta'])
        ->call('sort', 'name')
        ->assertSeeInOrder(['Beta', 'Alpha']);
});

it('creates a category and redirects to its editor', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.categories-index')
        ->set('name', 'Brand New')
        ->call('create')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.categories-edit', Category::query()->firstOrFail()));

    $this->assertDatabaseHas('translations', [
        'translatable_type' => 'category',
        'key' => 'name',
        'body' => 'Brand New',
    ]);
});

it('validates that a name is required when creating', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.categories-index')
        ->set('name', '')
        ->call('create')
        ->assertHasErrors(['name']);
});

it('can delete a category', function (): void {
    $category = resolve(CreateCategoryAction::class)->handle(['name' => ['en' => 'Doomed']]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.categories-index')
        ->call('delete', $category->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});
