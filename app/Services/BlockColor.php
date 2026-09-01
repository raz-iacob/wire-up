<?php

declare(strict_types=1);

namespace App\Services;

final class BlockColor
{
    public static function safe(mixed $value, string $default = ''): string
    {
        if (! is_string($value)) {
            return $default;
        }

        $value = mb_trim($value);

        if ($value === '' || preg_match('/^[#a-zA-Z0-9(),.%\s-]+$/', $value) !== 1) {
            return $default;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $declarations
     */
    public static function style(array $declarations): string
    {
        $parts = [];

        foreach ($declarations as $property => $value) {
            $color = self::safe($value);

            if ($color !== '') {
                $parts[] = "{$property}:{$color}";
            }
        }

        return implode(';', $parts);
    }
}
