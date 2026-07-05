<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BlockType;
use App\Services\SettingsService;
use Carbon\CarbonInterface;
use Database\Factories\BlockFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

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
     * @var array<int, string>
     */
    private const array PROSE_FIELDS = [
        'heading', 'subheading', 'intro', 'lead', 'body', 'quote', 'question',
        'answer', 'title', 'description', 'caption', 'label', 'value', 'role', 'bio',
    ];

    /**
     * @return array{provider: 'youtube'|'vimeo', id: string}|null
     */
    public static function parseVideoUrl(string $url): ?array
    {
        $url = mb_trim($url);

        if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~i', $url, $matches) === 1) {
            return ['provider' => 'youtube', 'id' => $matches[1]];
        }

        if (preg_match('~vimeo\.com/(?:video/|channels/[^/]+/|groups/[^/]+/videos/)?(\d+)~i', $url, $matches) === 1) {
            return ['provider' => 'vimeo', 'id' => $matches[1]];
        }

        return null;
    }

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

    public function plainText(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $default = config()->string('app.default_locale', 'en');

        $parts = [];
        $this->harvestText($this->content ?? [], $locale, $default, $parts);

        $spaced = (string) preg_replace('/<\/(?:p|div|li|h[1-6])>|<br\s*\/?>/i', ' ', implode(' ', $parts));
        $decoded = html_entity_decode(strip_tags($spaced), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = str_replace("\u{00A0}", ' ', $decoded);

        return (string) str($decoded)->squish();
    }

    /**
     * @param  array<string, int|string>  $params
     */
    public function imageUrl(string $field = 'image', array $params = [], string $cropKey = 'default'): ?string
    {
        $image = data_get($this->content, $field);

        if (! is_array($image) || empty($image['source'])) {
            return null;
        }

        /** @var array<string, int> $crop */
        $crop = is_array($image['crop'][$cropKey] ?? null) ? $image['crop'][$cropKey] : [];

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

        $caption = (string) data_get($image, 'metadata.caption', '');

        if ($caption !== '') {
            return $caption;
        }

        return (string) (data_get($image, 'metadata.alt', $image['alt_text'] ?? ''));
    }

    public function isVideo(string $field): bool
    {
        return str_starts_with((string) data_get($this->content, "{$field}.mime_type"), 'video/');
    }

    /**
     * @param  array<string, int|string>  $params
     */
    public function posterUrl(string $field, array $params = []): ?string
    {
        if (! $this->isVideo($field)) {
            return $this->imageUrl($field, $params);
        }

        $thumbnail = data_get($this->content, "{$field}.thumbnail");

        if (! is_string($thumbnail) || $thumbnail === '') {
            return null;
        }

        $options = sprintf(
            'w=%d,h=%d,q=%d,fm=%s',
            $params['w'] ?? 1200,
            $params['h'] ?? 800,
            $params['q'] ?? 80,
            $params['fm'] ?? 'jpg',
        );

        return route('image.show', ['options' => $options, 'path' => $thumbnail]);
    }

    public function fileUrl(string $field): ?string
    {
        $source = data_get($this->content, "{$field}.source");

        if (! is_string($source) || $source === '') {
            return null;
        }

        return Storage::disk(config()->string('filesystems.media'))->url($source);
    }

    /**
     * @return array{kind: 'native', src: string}|array{kind: 'iframe', provider: string, id: string}|null
     */
    public function videoEmbed(): ?array
    {
        $content = $this->content ?? [];

        if (($content['source'] ?? 'upload') === 'upload') {
            $src = $this->fileUrl('video');

            return $src !== null ? ['kind' => 'native', 'src' => $src] : null;
        }

        $url = mb_trim((string) ($content['url'] ?? ''));

        if ($url === '') {
            return null;
        }

        $parsed = self::parseVideoUrl($url);

        if ($parsed !== null) {
            return ['kind' => 'iframe', 'provider' => $parsed['provider'], 'id' => $parsed['id']];
        }

        return ['kind' => 'native', 'src' => $url];
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
            'page' => str_starts_with($value, SettingsService::AUTH_LINK_PREFIX)
                ? resolve(SettingsService::class)->resolveAuthLink($value)
                : Page::query()->whereKey($value)->first()?->getUrl(),
            default => $value,
        };
    }

    public function ctaOpensNewTab(string $field): bool
    {
        return ($this->content[$field]['link']['type'] ?? null) === 'url'
            && (bool) ($this->content[$field]['link']['newTab'] ?? false);
    }

    /**
     * @param  array<array-key, mixed>  $node
     * @param  array<int, string>  $parts
     */
    private function harvestText(array $node, string $locale, string $default, array &$parts): void
    {
        foreach ($node as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            $text = $value[$locale] ?? $value[$default] ?? null;

            if (is_string($key) && in_array($key, self::PROSE_FIELDS, true) && is_string($text)) {
                $parts[] = $text;

                continue;
            }

            $this->harvestText($value, $locale, $default, $parts);
        }
    }
}
