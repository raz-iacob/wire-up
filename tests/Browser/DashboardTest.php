<?php

declare(strict_types=1);

it('renders the dashboard islands without javascript errors', function (): void {
    $this->actingAsAdmin();

    $page = visit(route('admin.dashboard'));

    $page->assertNoJavascriptErrors()
        ->assertSee('Published items')
        ->assertSee('Content')
        ->assertSee('Recent activity')
        ->assertSee('New this period');
});
