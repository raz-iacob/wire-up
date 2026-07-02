<?php

declare(strict_types=1);

use App\Actions\CreateCategoryAction;

it('creates a category with a translatable name', function (): void {
    $category = resolve(CreateCategoryAction::class)->handle([
        'name' => ['en' => 'New Arrivals'],
    ]);

    $category->load('translations');

    expect($category->name)->toBe('New Arrivals');

    $this->assertDatabaseHas('translations', [
        'translatable_type' => 'category',
        'key' => 'name',
        'body' => 'New Arrivals',
    ]);
});
