<?php

declare(strict_types=1);

namespace App\Services;

final readonly class IntegrationTestResult
{
    private function __construct(
        public bool $passed,
        public string $message,
    ) {}

    public static function passed(string $message): self
    {
        return new self(true, $message);
    }

    public static function failed(string $message): self
    {
        return new self(false, $message);
    }
}
