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
            self::HERO => 'photo',
            self::TEXT_IMAGE => 'view-columns',
            self::SPACER => 'arrows-up-down',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultContent(): array
    {
        return match ($this) {
            self::HERO => ['align' => 'center'],
            self::TEXT_IMAGE => ['reverseLayout' => false],
            self::SPACER => ['size' => 'medium'],
        };
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
