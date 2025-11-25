<?php

declare(strict_types=1);

namespace App\Enums;

enum MediaType: string
{
    case AUDIO = 'audio';
    case DOCUMENT = 'document';
    case PHOTO = 'photo';
    case VIDEO = 'video';

    public function label(): string
    {
        return match ($this) {
            self::AUDIO => __('Audio'),
            self::DOCUMENT => __('Document'),
            self::PHOTO => __('Photo'),
            self::VIDEO => __('Video'),
        };
    }
}
