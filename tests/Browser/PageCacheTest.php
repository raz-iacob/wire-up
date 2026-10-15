<?php

declare(strict_types=1);

it('keeps livewire working on a page served from the saved copy', function (): void {
    connectStripe();
    $record = sellableRecord(attributes: ['stock' => 5]);

    visit($record->getUrl())->assertSee('Add to cart');

    $page = visit($record->getUrl());

    expect($page->script('fetch(location.href).then(response => response.headers.get("x-page-cache"))'))->toBe('hit');

    $page->assertSee('Add to cart')
        ->click('Add to cart')
        ->assertSee('View cart')
        ->assertNoJavascriptErrors();
});
