<?php

declare(strict_types=1);

namespace App\Enums;

enum ActivityArea: string
{
    case CONTENT = 'content';
    case PEOPLE = 'people';
    case SIGN_INS = 'sign_ins';
    case SETTINGS = 'settings';
    case SHOP = 'shop';
    case INBOX = 'inbox';
    case SYSTEM = 'system';

    public function label(): string
    {
        return match ($this) {
            self::CONTENT => __('Content'),
            self::PEOPLE => __('People'),
            self::SIGN_INS => __('Sign-ins'),
            self::SETTINGS => __('Settings'),
            self::SHOP => __('Shop'),
            self::INBOX => __('Inbox'),
            self::SYSTEM => __('System'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::CONTENT => 'document-text',
            self::PEOPLE => 'users',
            self::SIGN_INS => 'key',
            self::SETTINGS => 'cog-6-tooth',
            self::SHOP => 'shopping-bag',
            self::INBOX => 'inbox',
            self::SYSTEM => 'server-stack',
        };
    }

    /**
     * @return list<string>
     */
    public function events(): array
    {
        return array_values(array_map(
            fn (ActivityEvent $event): string => $event->value,
            array_filter(ActivityEvent::cases(), fn (ActivityEvent $event): bool => $event->area() === $this),
        ));
    }
}
