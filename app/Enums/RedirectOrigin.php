<?php

declare(strict_types=1);

namespace App\Enums;

enum RedirectOrigin: string
{
    case MANUAL = 'manual';
    case AUTOMATIC = 'automatic';
    case IMPORT = 'import';

    public function label(): string
    {
        return match ($this) {
            self::MANUAL => __('Manual'),
            self::AUTOMATIC => __('Automatic'),
            self::IMPORT => __('Imported'),
        };
    }
}
