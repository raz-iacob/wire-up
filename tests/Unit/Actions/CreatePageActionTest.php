<?php

declare(strict_types=1);

use App\Actions\CreatePageAction;
use App\Enums\ContentStatus;
use App\Models\Page;

it('may create a page', function (): void {
    $attributes = [
        'title' => 'Test Page',
    ];

    $page = new CreatePageAction()->handle($attributes);

    $page->refresh();

    expect($page)->toBeInstanceOf(Page::class)
        ->and($page->title)->toBe('Test Page')
        ->and($page->title)->toBe('Test Page')
        ->and($page->status)->toBe(ContentStatus::DRAFT)
        ->and($page->published_at)->toBeNull()
        ->and($page->slug)->toBe('test-page');
});

it('publishes a new page in the default locale by default', function (): void {
    $page = new CreatePageAction()->handle(['title' => 'Localized Page']);

    expect($page->refresh()->published_locales)->toBe([resolve('localization')->getDefaultLocale()]);
});
