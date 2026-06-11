<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MediaType;
use App\Traits\HasMedia;
use App\Traits\HasTranslations;
use Carbon\CarbonInterface;
use Database\Factories\SettingsFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * @property-read int $id
 * @property array<string, mixed>|null $metadata
 * @property-read Collection<int, Translation> $translations
 * @property-read string $title
 * @property-read string $description
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
final class Settings extends Model
{
    /** @use HasFactory<SettingsFactory> */
    use HasFactory, HasMedia, HasTranslations;

    protected $guarded = [];

    public static function current(): self
    {
        return self::query()->firstOrCreate([]);
    }

    public static function cached(): ?self
    {
        return once(fn (): ?self => Schema::hasTable('settings')
            ? self::query()->with(['translations', 'media'])->first()
            : null);
    }

    public function faviconUrl(string $crop = 'default'): ?string
    {
        $favicon = $this->media->first(fn (Media $media): bool => $media->type === MediaType::IMAGE
            && $media->pivot->role === 'favicon'
        );

        return $favicon ? $this->image('favicon', $crop, ['fm' => 'png'], false, $favicon) : null;
    }

    public function themeCss(): ?string
    {
        $meta = $this->metadata ?? [];

        $root = [];
        $dark = [];

        $theme = is_string($meta['theme'] ?? null) ? $meta['theme'] : null;

        if ($theme === 'custom' && is_string($meta['accent'] ?? null)) {
            $hex = $meta['accent'];
            $foreground = $this->readableForeground($hex);
            $root[] = "--color-accent:$hex";
            $root[] = "--color-accent-content:$hex";
            $root[] = "--color-accent-foreground:$foreground";
            $dark = $root;
        } elseif ($theme !== null && $theme !== 'custom' && array_key_exists($theme, config()->array('theme.colors'))) {
            $root[] = "--color-accent:var(--color-$theme-600)";
            $root[] = "--color-accent-content:var(--color-$theme-600)";
            $root[] = '--color-accent-foreground:#fff';
            $dark[] = "--color-accent:var(--color-$theme-500)";
            $dark[] = "--color-accent-content:var(--color-$theme-400)";
            $dark[] = '--color-accent-foreground:#fff';
        }

        if (is_string($meta['radius'] ?? null) && ($radius = config()->string("theme.radii.{$meta['radius']}", '')) !== '') {
            foreach (['sm', 'md', 'lg', 'xl'] as $size) {
                $root[] = "--radius-$size:$radius";
            }
        }

        if (($bodyStack = $this->fontStack($meta['body_font'] ?? null)) !== null) {
            $root[] = "--font-sans:$bodyStack";
        }

        if (is_string($meta['heading_size'] ?? null) && ($hs = config()->string("theme.heading_sizes.{$meta['heading_size']}", '')) !== '') {
            $root[] = "--site-heading-size:$hs";
        }

        if (is_string($meta['body_size'] ?? null) && ($bs = config()->string("theme.body_sizes.{$meta['body_size']}", '')) !== '') {
            $root[] = "--site-body-size:$bs";
        }

        $extra = '';
        if (($headingStack = $this->fontStack($meta['heading_font'] ?? null)) !== null) {
            $extra = "h1,h2,h3,h4,h5,h6,[data-flux-heading]{font-family:$headingStack}";
        }

        $css = '';
        $css .= $root === [] ? '' : ':root{'.implode(';', $root).'}';
        $css .= $dark === [] ? '' : '.dark{'.implode(';', $dark).'}';
        $css .= $extra;

        return $css === '' ? null : $css;
    }

    public function googleFontsUrl(): ?string
    {
        $meta = $this->metadata ?? [];

        $families = [];
        foreach (['heading_font', 'body_font'] as $slot) {
            $family = $this->fontGoogle($meta[$slot] ?? null);
            if ($family !== null) {
                $families[$family] = $family;
            }
        }

        if ($families === []) {
            return null;
        }

        $params = implode('&', array_map(
            fn (string $family): string => 'family='.str_replace(' ', '+', $family).':wght@400;500;600;700',
            $families,
        ));

        return "https://fonts.googleapis.com/css2?$params&display=swap";
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function translatedAttributes(): array
    {
        return ['title', 'description'];
    }

    private function fontStack(mixed $key): ?string
    {
        if (! is_string($key)) {
            return null;
        }

        $stack = config()->string("theme.fonts.$key.stack", '');

        return $stack === '' ? null : $stack;
    }

    private function fontGoogle(mixed $key): ?string
    {
        if (! is_string($key)) {
            return null;
        }

        $family = config("theme.fonts.$key.google");

        return is_string($family) && $family !== '' ? $family : null;
    }

    private function readableForeground(string $hex): string
    {
        $hex = mb_ltrim($hex, '#');

        if (mb_strlen($hex) !== 6) {
            return '#ffffff';
        }

        $luminance = (0.2126 * hexdec(mb_substr($hex, 0, 2))
            + 0.7152 * hexdec(mb_substr($hex, 2, 2))
            + 0.0722 * hexdec(mb_substr($hex, 4, 2))) / 255;

        return $luminance > 0.6 ? '#18181b' : '#ffffff';
    }
}
