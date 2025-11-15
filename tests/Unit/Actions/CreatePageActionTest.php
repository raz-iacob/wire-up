<?php

declare(strict_types=1);

use App\Actions\CreatePageAction;
use App\Enums\PageStatus;
use App\Models\Page;

it('may create a page', function (): void {
    $attributes = [
        'name' => 'Test Page',
    ];

    $page = new CreatePageAction()->handle($attributes);

    $page->refresh();

    expect($page)->toBeInstanceOf(Page::class)
        ->and($page->name)->toBe('Test Page')
        ->and($page->title)->toBe('Test Page')
        ->and($page->status)->toBe(PageStatus::DRAFT)
        ->and($page->published_at)->toBeNull()
        ->and($page->slug)->toBe('test-page');
});
