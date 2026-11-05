<?php

declare(strict_types=1);

namespace App\Services;

final readonly class NewsletterResult
{
    private function __construct(
        public bool $synced,
        public bool $retryable,
        public string $message,
    ) {}

    public static function synced(): self
    {
        return new self(true, false, '');
    }

    public static function failed(string $message, bool $retryable): self
    {
        return new self(false, $retryable, $message);
    }
}
