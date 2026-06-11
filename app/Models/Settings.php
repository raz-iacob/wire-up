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

    /**
     * @return array<string, string>
     */
    public function themeColors(): array
    {
        $theme = (string) data_get($this->metadata, 'theme', '');

        if ($theme === '') {
            return [];
        }

        $colors = $theme === 'custom'
            ? data_get($this->metadata, 'colors', [])
            : config()->array("theme.presets.$theme.colors", []);

        if (! is_array($colors)) {
            return [];
        }

        $palette = [];
        foreach ($colors as $slot => $hex) {
            if (is_string($slot) && is_string($hex)) {
                $palette[$slot] = $hex;
            }
        }

        return $palette;
    }

    public function themeCss(): ?string
    {
        $root = [];
        $dark = [];

        $colors = $this->themeColors();
        foreach ($colors as $slot => $hex) {
            $root[] = '--site-'.str_replace('_', '-', $slot).":$hex";
        }

        if (isset($colors['primary_bg'], $colors['primary_text'])) {
            $accent = [
                "--color-accent:{$colors['primary_bg']}",
                "--color-accent-content:{$colors['primary_bg']}",
                "--color-accent-foreground:{$colors['primary_text']}",
            ];
            $root = [...$root, ...$accent];
            $dark = $accent;
        }

        $radiusKey = (string) data_get($this->metadata, 'radius', '');
        $radius = config()->string("theme.radii.$radiusKey", '');
        if ($radius !== '') {
            foreach (['sm', 'md', 'lg', 'xl'] as $size) {
                $root[] = "--radius-$size:$radius";
            }
        }

        $bodyFont = (string) data_get($this->metadata, 'body_font', '');
        $bodyStack = config()->string("theme.fonts.$bodyFont.stack", '');
        if ($bodyStack !== '') {
            $root[] = "--font-sans:$bodyStack";
        }

        $headingSizeKey = (string) data_get($this->metadata, 'heading_size', '');
        $headingSize = config()->string("theme.heading_sizes.$headingSizeKey", '');
        if ($headingSize !== '') {
            $root[] = "--site-heading-size:$headingSize";
        }

        $bodySizeKey = (string) data_get($this->metadata, 'body_size', '');
        $bodySize = config()->string("theme.body_sizes.$bodySizeKey", '');
        if ($bodySize !== '') {
            $root[] = "--site-body-size:$bodySize";
        }

        $headingFont = (string) data_get($this->metadata, 'heading_font', '');
        $headingStack = config()->string("theme.fonts.$headingFont.stack", '');
        $extra = $headingStack === '' ? '' : "h1,h2,h3,h4,h5,h6,[data-flux-heading]{font-family:$headingStack}";

        $css = '';
        $css .= $root === [] ? '' : ':root{'.implode(';', $root).'}';
        $css .= $dark === [] ? '' : '.dark{'.implode(';', $dark).'}';
        $css .= $extra;

        return $css === '' ? null : $css;
    }

    public function googleFontsUrl(): ?string
    {
        $families = [];
        foreach (['heading_font', 'body_font'] as $slot) {
            $font = (string) data_get($this->metadata, $slot, '');
            $family = config()->string("theme.fonts.$font.google", '');
            if ($family !== '') {
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
}
