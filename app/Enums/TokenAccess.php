<?php

declare(strict_types=1);

namespace App\Enums;

enum TokenAccess: string
{
    case Read = 'read';
    case Edit = 'edit';
    case Full = 'full';

    public function label(): string
    {
        return match ($this) {
            self::Read => __('Read only'),
            self::Edit => __('Edit'),
            self::Full => __('Full access'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Read => __('Look at pages, records, media and settings.'),
            self::Edit => __('Also create and change them.'),
            self::Full => __('Also publish and delete.'),
        };
    }

    public function allows(self $needed): bool
    {
        return $this->rank() >= $needed->rank();
    }

    private function rank(): int
    {
        return match ($this) {
            self::Read => 1,
            self::Edit => 2,
            self::Full => 3,
        };
    }
}
