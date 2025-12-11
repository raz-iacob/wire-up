<?php

declare(strict_types=1);

namespace App\Enums;

enum MediaType: string
{
    case AUDIO = 'audio';
    case DOCUMENT = 'document';
    case IMAGE = 'image';
    case VIDEO = 'video';

    public static function fromMimeType(?string $mimeType): self
    {
        if ($mimeType === null) {
            return self::DOCUMENT;
        }

        return match (true) {
            str_starts_with($mimeType, 'image/') => self::IMAGE,
            str_starts_with($mimeType, 'video/') => self::VIDEO,
            str_starts_with($mimeType, 'audio/') => self::AUDIO,
            default => self::DOCUMENT,
        };
    }

    public function label(bool $plural = false): string
    {
        return match ($this) {
            self::AUDIO => $plural ? __('Audios') : __('Audio'),
            self::DOCUMENT => $plural ? __('Documents') : __('Document'),
            self::IMAGE => $plural ? __('Images') : __('Image'),
            self::VIDEO => $plural ? __('Videos') : __('Video'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::AUDIO => 'speaker-wave',
            self::DOCUMENT => 'document',
            self::IMAGE => 'photo',
            self::VIDEO => 'video-camera',
        };
    }
}
