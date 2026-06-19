<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BlockType;
use Carbon\CarbonInterface;
use Database\Factories\BlockFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property-read int $id
 * @property-read BlockType $type
 * @property-read int $position
 * @property-read array<string, mixed>|null $content
 * @property-read int $blockable_id
 * @property-read string $blockable_type
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Model $blockable
 */
final class Block extends Model
{
    /** @use HasFactory<BlockFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'type' => BlockType::class,
            'position' => 'integer',
            'content' => 'array',
            'blockable_id' => 'integer',
            'blockable_type' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function blockable(): MorphTo
    {
        return $this->morphTo();
    }

    public function text(string $field, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return (string) (
            data_get($this->content, "{$field}.{$locale}", data_get($this->content, $field.'.'.config()->string('app.default_locale', 'en'), ''))
        );
    }

    /**
     * @param  array<string, int|string>  $params
     */
    public function imageUrl(string $field = 'image', array $params = []): ?string
    {
        $image = data_get($this->content, $field);

        if (! is_array($image) || empty($image['source'])) {
            return null;
        }

        /** @var array<string, int> $crop */
        $crop = is_array($image['crop']['default'] ?? null) ? $image['crop']['default'] : [];

        $options = [
            'w' => $params['w'] ?? 1200,
            'q' => $params['q'] ?? 80,
            'fm' => $params['fm'] ?? 'jpg',
        ];

        if (isset($params['h'])) {
            $options['h'] = $params['h'];
        }

        $optionParts = [];

        foreach ($options as $key => $value) {
            $optionParts[] = "{$key}={$value}";
        }

        if (($crop['crop_w'] ?? 0) > 0 && ($crop['crop_h'] ?? 0) > 0) {
            $optionParts[] = sprintf('crop=%d-%d-%d-%d', $crop['crop_w'], $crop['crop_h'], $crop['crop_x'] ?? 0, $crop['crop_y'] ?? 0);
        }

        return route('image.show', [
            'options' => implode(',', $optionParts),
            'path' => $image['source'],
        ]);
    }

    public function imageAlt(string $field = 'image'): string
    {
        $image = data_get($this->content, $field);

        if (! is_array($image)) {
            return '';
        }

        return (string) (data_get($image, 'metadata.alt', $image['alt_text'] ?? ''));
    }

    public function ctaUrl(string $field): ?string
    {
        $link = data_get($this->content, "{$field}.link");

        if (! is_array($link)) {
            return null;
        }

        $value = mb_trim((string) ($link['value'] ?? ''));

        if ($value === '') {
            return null;
        }

        return match ($link['type'] ?? 'url') {
            'anchor' => '#'.mb_ltrim($value, '#'),
            'page' => Page::query()->whereKey($value)->first()?->getUrl(),
            default => $value,
        };
    }

    public function ctaOpensNewTab(string $field): bool
    {
        return ($this->content[$field]['link']['type'] ?? null) === 'url'
            && (bool) ($this->content[$field]['link']['newTab'] ?? false);
    }
}
