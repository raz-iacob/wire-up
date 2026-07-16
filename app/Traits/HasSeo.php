<?php

declare(strict_types=1);

namespace App\Traits;

trait HasSeo
{
    public function isNoindex(): bool
    {
        return (bool) ($this->metadata['noindex'] ?? false);
    }

    public function isMembersOnly(): bool
    {
        return (bool) ($this->metadata['members_only'] ?? false);
    }

    public function textExcerpt(int $chars = 160, ?string $locale = null): string
    {
        return str($this->plainText($locale))->limit($chars, '')->trim()->value();
    }
}
