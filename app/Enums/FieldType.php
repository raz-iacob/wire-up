<?php

declare(strict_types=1);

namespace App\Enums;

enum FieldType: string
{
    case TEXT = 'text';
    case TEXTAREA = 'textarea';
    case RICH_TEXT = 'rich-text';
    case NUMBER = 'number';
    case DATE = 'date';
    case DATETIME = 'datetime';
    case BOOLEAN = 'boolean';
    case SELECT = 'select';
    case PHOTO = 'photo';
    case VIDEO = 'video';
    case AUDIO = 'audio';
    case DOCUMENT = 'document';
    case MEDIA_GALLERY = 'media-gallery';
    case URL = 'url';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type): string => $type->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::TEXT => __('Text'),
            self::TEXTAREA => __('Text area'),
            self::RICH_TEXT => __('Rich text'),
            self::NUMBER => __('Number'),
            self::DATE => __('Date'),
            self::DATETIME => __('Date & time'),
            self::BOOLEAN => __('Toggle'),
            self::SELECT => __('Dropdown'),
            self::PHOTO => __('Photo'),
            self::VIDEO => __('Video'),
            self::AUDIO => __('Audio'),
            self::DOCUMENT => __('Document'),
            self::MEDIA_GALLERY => __('Media gallery'),
            self::URL => __('Link'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::TEXT => 'bars-3-bottom-left',
            self::TEXTAREA => 'bars-4',
            self::RICH_TEXT => 'document-text',
            self::NUMBER => 'hashtag',
            self::DATE => 'calendar',
            self::DATETIME => 'clock',
            self::BOOLEAN => 'check-circle',
            self::SELECT => 'chevron-up-down',
            self::PHOTO => 'photo',
            self::VIDEO => 'video-camera',
            self::AUDIO => 'speaker-wave',
            self::DOCUMENT => 'document',
            self::MEDIA_GALLERY => 'squares-2x2',
            self::URL => 'link',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::TEXT => __('A single line of text.'),
            self::TEXTAREA => __('Multiple lines of plain text.'),
            self::RICH_TEXT => __('Formatted text with headings, links and lists.'),
            self::NUMBER => __('A numeric value.'),
            self::DATE => __('A calendar date.'),
            self::DATETIME => __('A date together with a time.'),
            self::BOOLEAN => __('An on/off switch.'),
            self::SELECT => __('A dropdown with predefined options.'),
            self::PHOTO => __('A single image.'),
            self::VIDEO => __('A single video.'),
            self::AUDIO => __('A single audio file.'),
            self::DOCUMENT => __('A single document or file.'),
            self::MEDIA_GALLERY => __('A gallery of images and videos.'),
            self::URL => __('A web address.'),
        };
    }

    public function isTranslatableByDefault(): bool
    {
        return match ($this) {
            self::TEXT, self::TEXTAREA, self::RICH_TEXT, self::SELECT => true,
            default => false,
        };
    }

    public function supportsOptions(): bool
    {
        return $this === self::SELECT;
    }

    /**
     * @return array<int, string>
     */
    public function acceptsMedia(): array
    {
        return match ($this) {
            self::PHOTO => [MediaType::IMAGE->value],
            self::VIDEO => [MediaType::VIDEO->value],
            self::AUDIO => [MediaType::AUDIO->value],
            self::DOCUMENT => [MediaType::DOCUMENT->value],
            self::MEDIA_GALLERY => [MediaType::IMAGE->value, MediaType::VIDEO->value],
            default => [],
        };
    }

    public function isMedia(): bool
    {
        return $this->acceptsMedia() !== [];
    }

    public function isGallery(): bool
    {
        return $this === self::MEDIA_GALLERY;
    }
}
