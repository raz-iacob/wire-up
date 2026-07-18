<?php

declare(strict_types=1);

use Livewire\Livewire;

it('renders the help screen for authenticated admins', function (): void {
    $this->actingAsAdmin()
        ->get(route('admin.help'))
        ->assertOk()
        ->assertSeeLivewire('pages::admin.help')
        ->assertSee('Help & Support');
});

it('redirects guests away from help', function (): void {
    $this->get(route('admin.help'))
        ->assertRedirect();
});

it('shows every faq by default', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.help')
        ->assertCount('results', 16)
        ->assertSee('Where do I start after setting up my site?');
});

it('filters faqs by topic', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.help')
        ->set('topic', 'users')
        ->assertCount('results', 2)
        ->assertSee('How do I invite a team member?')
        ->assertDontSee('How do I connect Google Analytics?');
});

it('filters faqs by search across the question and answer', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.help')
        ->set('search', 'favicon')
        ->assertCount('results', 1)
        ->assertSee('Can I use my own logo and favicon?');
});

it('returns no results when nothing matches', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.help')
        ->set('search', 'zzzznomatch')
        ->assertCount('results', 0)
        ->assertSee('No results found');
});
