<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Block;
use App\Models\Media;
use App\Models\Mediable;
use App\Models\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class MediaUsageService
{
    public function isInUse(Media $media): bool
    {
        if ($media->mediables()->exists()) {
            return true;
        }
        if ($this->blockQuery($media)->exists()) {
            return true;
        }

        return $this->settingsQuery($media)->exists();
    }

    /**
     * @return array<int, string>
     */
    public function labels(Media $media): array
    {
        $pivot = $media->mediables()
            ->with('mediable')
            ->get()
            ->map(fn (Mediable $mediable): Model => $mediable->mediable)
            ->map(fn (Model $owner): string => $this->ownerLabel($owner))
            ->all();

        $blocks = $this->blockQuery($media)
            ->with('blockable')
            ->get()
            ->map(fn (Block $block): string => $this->blockLabel($block))
            ->all();

        $settings = $this->settingsQuery($media)->exists()
            ? [__('Site settings')]
            : [];

        return collect([...$pivot, ...$blocks, ...$settings])
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Builder<Block>
     */
    private function blockQuery(Media $media): Builder
    {
        return Block::query()->where('content', 'like', '%'.$this->needle($media).'%');
    }

    /**
     * @return Builder<Settings>
     */
    private function settingsQuery(Media $media): Builder
    {
        return Settings::query()->where('value', 'like', '%'.$this->needle($media).'%');
    }

    private function needle(Media $media): string
    {
        return basename($media->source);
    }

    private function ownerLabel(Model $owner): string
    {
        $type = class_basename($owner);
        $label = $owner->getAttribute('title') ?? $owner->getAttribute('name');

        return is_string($label) && $label !== ''
            ? $type.': '.$label
            : $type.' #'.$owner->getKey();
    }

    private function blockLabel(Block $block): string
    {
        return $this->ownerLabel($block->blockable).' — '.$block->type->label();
    }
}
