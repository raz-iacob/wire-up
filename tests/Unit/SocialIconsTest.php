<?php

declare(strict_types=1);

it('has both icon variant files for every configured social platform', function (): void {
    $variants = array_keys(config()->array('social.icon_variants'));

    foreach (config()->array('social.platforms') as $platform => $meta) {
        foreach ($variants as $variant) {
            $path = resource_path("images/socials/{$meta['icon']}-{$variant}.svg");

            expect(file_exists($path))->toBeTrue("Missing {$variant} icon for {$platform} at {$path}");
        }
    }
});
