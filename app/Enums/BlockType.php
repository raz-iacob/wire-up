<?php

declare(strict_types=1);

namespace App\Enums;

enum BlockType: string
{
    case HERO = 'hero';
    case TEXT_IMAGE = 'text-image';
    case SPACER = 'spacer';

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
            self::HERO => __('Hero'),
            self::TEXT_IMAGE => __('Text + Image'),
            self::SPACER => __('Spacer'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::HERO => 'gallery-thumbnails',
            self::TEXT_IMAGE => 'view-columns',
            self::SPACER => 'arrows-up-down',
        };
    }

    public function hasAnchor(): bool
    {
        return $this !== self::SPACER;
    }

    public function description(): string
    {
        return match ($this) {
            self::HERO => __('Full-width banner with a heading, subheading and background image.'),
            self::TEXT_IMAGE => __('A block of text alongside an image.'),
            self::SPACER => __('Adjustable vertical spacing between blocks.'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultContent(): array
    {
        $cta = [
            'enabled' => false,
            'text' => [],
            'link' => ['type' => 'url', 'value' => '', 'newTab' => false],
            'bg' => null,
            'textColor' => null,
        ];

        return match ($this) {
            self::HERO => [
                'align' => 'center',
                'verticalAlign' => 'center',
                'width' => 'full',
                'height' => 'auto',
                'headingColor' => null,
                'subheadingColor' => null,
                'background' => [
                    'type' => 'image',
                    'image' => null,
                    'gradient' => ['start' => null, 'end' => null, 'direction' => 'to-b'],
                ],
                'ctaPrimary' => $cta,
                'ctaSecondary' => $cta,
            ],
            self::TEXT_IMAGE => ['reverseLayout' => false],
            self::SPACER => ['size' => 'medium'],
        };
    }

    /**
     * @param  array<string, mixed>  $content
     */
    public function editorTitle(array $content, string $locale): string
    {
        $text = match ($this) {
            self::HERO => str(strip_tags((string) data_get($content, "heading.{$locale}")))->squish()->limit(50)->value(),
            self::TEXT_IMAGE => str(strip_tags((string) data_get($content, "body.{$locale}")))->squish()->words(8, '…')->value(),
            default => '',
        };

        return $text !== '' ? $text : $this->label();
    }

    public function adminView(): string
    {
        return "components.admin.blocks.{$this->value}";
    }

    public function frontendView(): string
    {
        return "components.site.blocks.{$this->value}";
    }
}
