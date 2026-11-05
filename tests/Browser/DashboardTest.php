<?php

declare(strict_types=1);

use App\Models\User;

it('renders the dashboard islands without javascript errors', function (): void {
    $this->actingAsAdmin();

    $page = visit(route('admin.dashboard'));

    $page->assertNoJavascriptErrors()
        ->assertSee('Published items')
        ->assertSee('Content')
        ->assertSee('Recent activity')
        ->assertSee('New this period');
});

it('keeps dark mode colours steady while moving to another admin screen', function (): void {
    $this->actingAs(User::factory()->owner()->create(['active' => true, 'metadata' => ['appearance' => 'dark']]));

    $page = visit(route('admin.settings-general'));

    $page->script("window.colourTransitions = []; document.addEventListener('transitionrun', (event) => window.colourTransitions.push(event.propertyName), true); window.Livewire.navigate('/admin'); void 0");

    $page->assertSee('Unread messages')
        ->assertScript("document.documentElement.classList.contains('dark')", true)
        ->wait(0.5);

    $page->assertNoJavascriptErrors()
        ->assertScript("window.colourTransitions.filter((property) => property.includes('color')).join(', ')", '')
        ->assertScript("getComputedStyle(document.querySelector('[class~=\"transition\"]')).transitionProperty !== 'none'", true);
});
