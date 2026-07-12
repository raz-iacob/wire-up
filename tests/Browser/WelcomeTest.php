<?php

declare(strict_types=1);

it('shows the welcome landing page without javascript errors', function (): void {
    $page = visit('/');

    $page->assertNoJavascriptErrors()
        ->assertSee('Documentation')
        ->assertSee('Getting started')
        ->assertDontSee('Made with Wire-Up');
});
