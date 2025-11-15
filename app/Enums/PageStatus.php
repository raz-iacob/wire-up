<?php

declare(strict_types=1);

namespace App\Enums;

enum PageStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case PRIVATE = 'private';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PUBLISHED => 'Published',
            self::PRIVATE => 'Private',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'zinc',
            self::PUBLISHED => 'green',
            self::PRIVATE => 'orange',
        };
    }
}
