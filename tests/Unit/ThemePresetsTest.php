<?php

declare(strict_types=1);

it('gives every preset a light and a dark palette covering every slot', function (): void {
    $slots = array_keys(config()->array('theme.slots'));
    $incomplete = [];

    foreach (config()->array('theme.presets') as $key => $preset) {
        foreach (['colors', 'colors_dark'] as $palette) {
            $missing = array_diff($slots, array_keys($preset[$palette] ?? []));

            if ($missing !== []) {
                $incomplete[] = "{$key}.{$palette}: ".implode(', ', $missing);
            }
        }
    }

    expect($incomplete)->toBe([]);
});

it('only uses six-digit hex colours', function (): void {
    $bad = [];

    foreach (config()->array('theme.presets') as $key => $preset) {
        foreach (['colors', 'colors_dark'] as $palette) {
            foreach ($preset[$palette] as $slot => $hex) {
                if (preg_match('/^#[0-9a-f]{6}$/', (string) $hex) !== 1) {
                    $bad[] = "{$key}.{$palette}.{$slot} = {$hex}";
                }
            }
        }
    }

    expect($bad)->toBe([]);
});

it('keeps a dark palette darker than its light counterpart', function (): void {
    $luminance = function (string $hex): float {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        return (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
    };

    $wrongWayRound = [];

    foreach (config()->array('theme.presets') as $key => $preset) {
        if ($luminance($preset['colors_dark']['background']) >= $luminance($preset['colors']['background'])) {
            $wrongWayRound[] = $key;
        }
    }

    expect($wrongWayRound)->toBe([]);
});

it('ships the blueprint preset in place of midnight', function (): void {
    $presets = config()->array('theme.presets');

    expect($presets)->toHaveKey('blueprint')
        ->and($presets)->not->toHaveKey('midnight')
        ->and($presets['blueprint']['colors_dark']['accent'])->toBe('#38b6ff');
});

it('gives every hero a band you can actually see against the page', function (): void {
    $luminance = function (string $hex): float {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        $channel = fn (float $c): float => ($c /= 255) <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;

        return 0.2126 * $channel((float) $r) + 0.7152 * $channel((float) $g) + 0.0722 * $channel((float) $b);
    };

    $ratio = function (string $a, string $b) use ($luminance): float {
        $first = $luminance($a);
        $second = $luminance($b);

        return (max($first, $second) + 0.05) / (min($first, $second) + 0.05);
    };

    $flat = [];
    $unreadable = [];

    foreach (config()->array('theme.presets') as $key => $preset) {
        foreach (['colors', 'colors_dark'] as $palette) {
            $p = $preset[$palette];

            if ($ratio($p['hero_bg'], $p['background']) < 1.15) {
                $flat[] = "{$key}.{$palette}";
            }

            if ($ratio($p['hero_text'], $p['hero_bg']) < 4.5) {
                $unreadable[] = "{$key}.{$palette}";
            }
        }
    }

    expect($flat)->toBe([])
        ->and($unreadable)->toBe([]);
});

it('keeps the primary button visible against the hero band', function (): void {
    $luminance = function (string $hex): float {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        $channel = fn (float $c): float => ($c /= 255) <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;

        return 0.2126 * $channel((float) $r) + 0.7152 * $channel((float) $g) + 0.0722 * $channel((float) $b);
    };

    $ratio = function (string $a, string $b) use ($luminance): float {
        $first = $luminance($a);
        $second = $luminance($b);

        return (max($first, $second) + 0.05) / (min($first, $second) + 0.05);
    };

    $lost = [];

    foreach (config()->array('theme.presets') as $key => $preset) {
        foreach (['colors', 'colors_dark'] as $palette) {
            $p = $preset[$palette];

            if ($ratio($p['primary_bg'], $p['hero_bg']) < 1.6) {
                $lost[] = "{$key}.{$palette}";
            }
        }
    }

    expect($lost)->toBe([]);
});
