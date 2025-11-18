<?php

declare(strict_types=1);

namespace App\Enums;

enum PageStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case PRIVATE = 'private';
    case SCHEDULED = 'scheduled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => __('Draft'),
            self::PUBLISHED => __('Published'),
            self::PRIVATE => __('Private'),
            self::SCHEDULED => __('Scheduled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'zinc',
            self::PUBLISHED => 'green',
            self::PRIVATE => 'orange',
            self::SCHEDULED => 'blue',
        };
    }
}
