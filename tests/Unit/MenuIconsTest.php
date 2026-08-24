<?php

declare(strict_types=1);

it('only offers icons that ship with a Flux view', function (): void {
    $missing = array_values(array_filter(
        config()->array('menu.icons'),
        fn (string $icon): bool => ! file_exists(base_path("vendor/livewire/flux/stubs/resources/views/flux/icon/{$icon}.blade.php")),
    ));

    expect($missing)->toBe([]);
});

it('offers each icon once', function (): void {
    $icons = config()->array('menu.icons');

    expect($icons)->toBe(array_values(array_unique($icons)));
});
