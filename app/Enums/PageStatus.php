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
            self::PRIVATE => 'red',
            self::SCHEDULED => 'orange',
        };
    }

    public function textColor(): string
    {
        return match ($this) {
            self::DRAFT => 'text-zinc-500 dark:text-zinc-400',
            self::PUBLISHED => 'text-green-600 dark:text-green-400',
            self::PRIVATE => 'text-red-500 dark:text-red-400',
            self::SCHEDULED => 'text-orange-500 dark:text-orange-400',
        };
    }
}
