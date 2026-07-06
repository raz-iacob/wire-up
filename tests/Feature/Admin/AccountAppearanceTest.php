<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Livewire;

it('can render the account appearance screen', function (): void {
    $response = $this->actingAsAdmin()
        ->fromRoute('home')
        ->get(route('admin.account-appearance'));

    $response->assertOk()
        ->assertSeeLivewire('pages::admin.account-appearance');
});

it('redirects authenticated non-admin users away from account appearance', function (): void {
    $user = User::factory()->create([
        'active' => true,
        'role' => 'member',
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('home')
        ->get(route('admin.account-appearance'));

    $response->assertRedirectToRoute('home');
});

it('redirects guests away from account appearance', function (): void {
    $response = $this->fromRoute('home')
        ->get(route('admin.account-appearance'));

    $response->assertRedirectToRoute('login');
});

it('hydrates the saved appearance preference from user metadata', function (): void {
    $user = User::factory()->owner()->create(['metadata' => ['appearance' => 'dark']]);

    Livewire::actingAs($user)
        ->test('pages::admin.account-appearance')
        ->assertSet('appearance', 'dark');
});

it('defaults to system when no appearance preference is stored', function (): void {
    $user = User::factory()->owner()->create(['metadata' => []]);

    Livewire::actingAs($user)
        ->test('pages::admin.account-appearance')
        ->assertSet('appearance', 'system');
});

it('persists the appearance preference to user metadata', function (): void {
    $user = User::factory()->owner()->create(['metadata' => ['phone' => '123']]);

    Livewire::actingAs($user)
        ->test('pages::admin.account-appearance')
        ->set('appearance', 'dark')
        ->assertHasNoErrors();

    expect($user->fresh()->metadata)->toMatchArray([
        'phone' => '123',
        'appearance' => 'dark',
    ]);
});

it('rejects an unknown appearance value', function (): void {
    $user = User::factory()->owner()->create();

    Livewire::actingAs($user)
        ->test('pages::admin.account-appearance')
        ->set('appearance', 'neon')
        ->assertHasErrors(['appearance']);

    expect(data_get($user->fresh()->metadata, 'appearance'))->not->toBe('neon');
});

it('seeds the stored appearance preference into local storage on admin pages', function (): void {
    $user = User::factory()->owner()->create(['metadata' => ['appearance' => 'dark']]);

    $this->actingAs($user)
        ->get(route('admin.account-appearance'))
        ->assertOk()
        ->assertSee("localStorage.setItem('flux.appearance', preference)", false)
        ->assertSee('"dark"', false);
});

it('does not seed local storage when no appearance preference is stored', function (): void {
    $user = User::factory()->owner()->create(['metadata' => []]);

    $this->actingAs($user)
        ->get(route('admin.account-appearance'))
        ->assertOk()
        ->assertDontSee("localStorage.setItem('flux.appearance', preference)", false);
});
