<?php

declare(strict_types=1);

namespace App\Enums;

enum FieldPlacement: string
{
    case MAIN = 'main';
    case SIDEBAR = 'sidebar';
    case HIDDEN = 'hidden';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $placement): string => $placement->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::MAIN => __('Main'),
            self::SIDEBAR => __('Sidebar'),
            self::HIDDEN => __('Hidden'),
        };
    }
}
