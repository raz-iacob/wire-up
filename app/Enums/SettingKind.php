<?php

declare(strict_types=1);

namespace App\Enums;

enum SettingKind: string
{
    case SECRET = 'secret';
    case PRIVATE = 'private';
    case EXPORTED = 'exported';
}
