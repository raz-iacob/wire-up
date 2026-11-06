<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('keeps every Flux component the public site renders in the site stylesheet', function (): void {
    $stylesheet = File::get(resource_path('css/site.css'));

    preg_match_all("#@source not '\.\./\.\./vendor/livewire/flux(?:-pro)?/stubs/resources/views/flux/([a-z-]+)(?:\.blade\.php)?';#", $stylesheet, $excluded);
    preg_match_all("#@source '\.\./views/([^']+)';#", $stylesheet, $sources);

    $queue = collect($sources[1])
        ->flatMap(fn (string $source): array => File::isDirectory(resource_path('views/'.$source))
            ? array_map(fn (SplFileInfo $file): string => $file->getPathname(), File::allFiles(resource_path('views/'.$source)))
            : File::glob(resource_path('views/'.$source)))
        ->all();

    $roots = [base_path('vendor/livewire/flux/stubs/resources/views/flux'), base_path('vendor/livewire/flux-pro/stubs/resources/views/flux')];
    $rendered = [];

    while ($queue !== []) {
        preg_match_all('#<flux:([a-z][a-z0-9.-]*)#', File::get(array_pop($queue)), $matches);

        foreach (array_diff($matches[1], array_keys($rendered)) as $component) {
            $rendered[$component] = true;
            $path = str_replace('.', '/', $component);

            foreach ($roots as $root) {
                $queue = [...$queue, ...array_filter(["{$root}/{$path}.blade.php", "{$root}/{$path}/index.blade.php"], File::exists(...))];
            }
        }
    }

    $components = array_unique(array_map(fn (string $component): string => explode('.', $component)[0], array_keys($rendered)));

    expect($excluded[1])->not->toBeEmpty()
        ->and($components)->toContain('button')
        ->and(array_values(array_intersect($components, $excluded[1])))->toBeEmpty();
});
