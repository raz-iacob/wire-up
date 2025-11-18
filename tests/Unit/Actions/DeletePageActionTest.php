<?php

declare(strict_types=1);

use App\Actions\DeletePageAction;
use App\Models\Page;

it('may delete a page', function (): void {
    $page = Page::factory()->create([
        'title' => 'Sample Page',
    ]);

    $page->setSlugs();

    $action = app(DeletePageAction::class);

    $action->handle($page);

    expect($page->exists)->toBeFalse()
        ->and($page->slugs()->count())->toBe(0)
        ->and($page->translations()->count())->toBe(0);
});
