<?php

declare(strict_types=1);

use App\Actions\CreateCategoryAction;
use App\Actions\UpdateCategoryAction;

it('updates the name of a category', function (): void {
    $category = resolve(CreateCategoryAction::class)->handle(['name' => ['en' => 'Old']]);

    resolve(UpdateCategoryAction::class)->handle($category, [
        'name' => ['en' => 'Renamed'],
    ]);

    $category->refresh()->load('translations');

    expect($category->name)->toBe('Renamed');
});
