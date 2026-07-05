<?php

declare(strict_types=1);

namespace App\Enums;

enum PermissionAction: string
{
    case View = 'view';
    case Create = 'create';
    case Edit = 'edit';
    case Delete = 'delete';

    public function label(): string
    {
        return match ($this) {
            self::View => __('View'),
            self::Create => __('Create'),
            self::Edit => __('Edit'),
            self::Delete => __('Delete'),
        };
    }
}
