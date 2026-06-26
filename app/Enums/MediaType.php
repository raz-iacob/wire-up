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

    /**
     * @return array<int, string>
     */
    public function allowedMimeTypes(): array
    {
        return match ($this) {
            self::IMAGE => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/heic', 'image/heif'],
            self::VIDEO => ['video/mp4', 'video/quicktime', 'video/x-msvideo'],
            self::AUDIO => ['audio/mpeg', 'audio/wav', 'audio/ogg'],
            self::DOCUMENT => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/vnd.oasis.opendocument.text',
                'application/vnd.oasis.opendocument.spreadsheet',
                'application/vnd.oasis.opendocument.presentation',
                'application/epub+zip',
                'text/plain',
                'text/csv',
                'application/csv',
                'application/rtf',
                'text/rtf',
                'application/zip',
                'application/x-rar-compressed',
                'application/vnd.rar',
                'application/x-7z-compressed',
                'application/x-tar',
                'application/gzip',
                'application/x-gzip',
            ],
        };
    }
}
