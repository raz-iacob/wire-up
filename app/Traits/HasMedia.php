<?php

declare(strict_types=1);

namespace App\Traits;

use App\Enums\MediaType;
use App\Models\Media;
use App\Models\Mediable;
use App\Services\ImageService;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Arr;

trait HasMedia
{
    /**
     * @return MorphToMany<Media, $this, Mediable>
     */
    public function media(): MorphToMany
    {
        return $this->morphToMany(Media::class, 'mediable', 'mediables')
            ->using(Mediable::class)
            ->withPivot(['role', 'crop', 'metadata', 'locale', 'position'])
            ->withTimestamps()
            ->orderBy('mediables.position')
            ->orderByDesc('mediables.created_at');
    }

    public function firstMedia(MediaType $type, string $role): ?Media
    {
        return $this->media->sortBy('mediables.position')->first(fn (Media $media): bool => $media->type === $type
            && $media->pivot->role === $role
            && $media->pivot->locale === app()->getLocale()
        );
    }

    /**
     * @return array<int, Media>
     */
    public function allMedia(MediaType $type, string $role): array
    {
        return $this->media
            ->filter(fn (Media $media): bool => $media->type === $type
                && $media->pivot->role === $role
                && $media->pivot->locale === app()->getLocale()
            )
            ->values()
            ->all();
    }

    public function hasImage(string $role, string $crop = 'default'): bool
    {
        return (bool) $this->findImage($role, $crop);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function image(string $role, string $crop = 'default', array $params = [], bool $fallback = true, ?Media $media = null): ?string
    {
        $media ??= $this->findImage($role, $crop);

        return $media ?
            route('image.show', [
                'options' => $this->cropString([...($media->pivot->crop[$crop] ?? []), ...$params]),
                'path' => $media->url,
            ]) : ($fallback ? ImageService::placeholder() : null);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<int, string>
     */
    public function images(string $role, string $crop = 'default', array $params = []): array
    {
        return $this->media
            ->filter(fn (Media $media): bool => $media->type === MediaType::PHOTO
                && $media->pivot->role === $role
                && ($media->pivot->crop[$crop] ?? false)
                && $media->pivot->locale === app()->getLocale()
            )
            ->map(fn (Media $media): string => $this->image($role, $crop, $params, false, $media))
            ->values()
            ->all();
    }

    public function imageAltText(string $role, ?Media $media = null): string
    {
        $media ??= $this->findImage($role);

        return $media->alt_text ?? '';
    }

    public function imageCaption(string $role, ?Media $media = null): string
    {
        $media ??= $this->findImage($role);

        return $media->pivot->metadata['caption'] ?? '';
    }

    protected static function bootHasMedia(): void
    {
        static::deleted(fn (self $model) => $model->media()->detach());
    }

    private function findImage(string $role, string $crop = 'default'): ?Media
    {
        return $this->media->first(fn (Media $media): bool => $media->type === MediaType::PHOTO
            && $media->pivot->role === $role
            && ($media->pivot->crop[$crop] ?? false)
            && $media->pivot->locale === app()->getLocale()
        );
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function cropString(array $params): string
    {
        $default = [
            'crop_w' => 1200,
            'crop_h' => 800,
            'crop_x' => 0,
            'crop_y' => 0,
            'w' => 1200,
            'h' => 800,
            'q' => 80,
            'fm' => 'jpg',
        ];

        $values = [...$default, ...Arr::only($params, array_keys($default))];

        $crop = sprintf(
            '%d-%d-%d-%d',
            $values['crop_w'],
            $values['crop_h'],
            $values['crop_x'],
            $values['crop_y']
        );

        return implode(',', [
            "w={$values['w']}",
            "h={$values['h']}",
            "crop={$crop}",
            "q={$values['q']}",
            "fm={$values['fm']}",
        ]);
    }
}
