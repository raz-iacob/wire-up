<?php

declare(strict_types=1);

use App\Actions\CreateCategoryAction;
use App\Models\User;
use Livewire\Livewire;

it('can render the category edit screen', function (): void {
    $category = resolve(CreateCategoryAction::class)->handle(['name' => ['en' => 'Featured']]);

    $this->actingAsAdmin()
        ->fromRoute('admin.categories-index')
        ->get(route('admin.categories-edit', $category))
        ->assertOk()
        ->assertSeeLivewire('pages::admin.categories-edit');
});

it('redirects authenticated non-admin users away from category edit', function (): void {
    $category = resolve(CreateCategoryAction::class)->handle(['name' => ['en' => 'Featured']]);
    $user = User::factory()->create(['active' => true, 'admin' => false]);

    $this->actingAs($user)
        ->fromRoute('home')
        ->get(route('admin.categories-edit', $category))
        ->assertRedirectToRoute('home');
});

it('redirects guests away from category edit', function (): void {
    $category = resolve(CreateCategoryAction::class)->handle(['name' => ['en' => 'Featured']]);

    $this->fromRoute('home')
        ->get(route('admin.categories-edit', $category))
        ->assertRedirectToRoute('login');
});

it('populates the name on mount', function (): void {
    $category = resolve(CreateCategoryAction::class)->handle(['name' => ['en' => 'Featured']]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.categories-edit', ['category' => $category])
        ->assertSet('category.id', $category->id)
        ->assertSet('name.en', 'Featured');
});

it('updates the category name', function (): void {
    $category = resolve(CreateCategoryAction::class)->handle(['name' => ['en' => 'Old']]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.categories-edit', ['category' => $category])
        ->set('name.en', 'Renamed')
        ->call('update')
        ->assertHasNoErrors();

    expect($category->refresh()->name)->toBe('Renamed');
});

it('requires a name for the default locale', function (): void {
    $category = resolve(CreateCategoryAction::class)->handle(['name' => ['en' => 'Old']]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.categories-edit', ['category' => $category])
        ->set('name.en', '')
        ->call('update')
        ->assertHasErrors(['name.en']);
});
