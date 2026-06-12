<?php

declare(strict_types=1);

it('renders the identity settings screen with no js errors', function (): void {
    $this->actingAsAdmin();

    $page = visit(route('admin.settings-identity'));

    $page->assertNoJavascriptErrors()
        ->assertSee('Title')
        ->assertSee('Tagline');
});
