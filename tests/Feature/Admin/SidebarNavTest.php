<?php

declare(strict_types=1);

use App\Models\RecordType;
use Livewire\Livewire;

it('renders a nav item for each record type ordered by position', function (): void {
    RecordType::factory()->create(['name' => 'Products', 'slug_prefix' => 'products', 'icon' => 'shopping-bag', 'position' => 1]);
    RecordType::factory()->create(['name' => 'Services', 'slug_prefix' => 'services', 'icon' => 'wrench-screwdriver', 'position' => 0]);

    $this->actingAsAdmin();

    Livewire::test('admin.sidebar-nav')
        ->assertSeeInOrder(['Services', 'Products']);
});

it('renders nothing when there are no record types', function (): void {
    $this->actingAsAdmin();

    Livewire::test('admin.sidebar-nav')
        ->assertDontSee(__('Content'));
});

it('refreshes the nav when content types change', function (): void {
    $this->actingAsAdmin();

    $component = Livewire::test('admin.sidebar-nav')->assertDontSee('Products');

    RecordType::factory()->create(['name' => 'Products', 'slug_prefix' => 'products']);

    $component->dispatch('content-types-updated')->assertSee('Products');
});
