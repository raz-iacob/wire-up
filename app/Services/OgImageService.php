<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Page;
use App\Models\Record;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Throwable;

final readonly class OgImageService
{
    public function __construct(private PageScreenshot $screenshot) {}

    public function url(Page|Record $content, string $locale): ?string
    {
        $file = $this->file($content, $locale);

        if ($file === null) {
            return null;
        }

        return URL::route('og.show', [
            'type' => $this->key($content),
            'id' => $content->id,
            'locale' => $locale,
            'v' => Str::before(Str::afterLast(basename($file), '-'), '.png'),
        ]);
    }

    public function generate(Page|Record $content, string $locale): bool
    {
        $fingerprint = $this->fingerprint($content, $locale);

        if ($fingerprint === null) {
            return false;
        }

        $file = $this->fileFor($content, $locale, $fingerprint);

        if (is_file($file)) {
            return true;
        }

        $png = $this->capture($this->card($this->title($content, $locale)));

        if ($png === null) {
            return false;
        }

        File::ensureDirectoryExists(dirname($file));

        foreach (File::glob($this->directory($content).'/'.$locale.'-*.png') ?: [] as $stale) {
            File::delete($stale);
        }

        File::put($file, $png);

        return true;
    }

    public function file(Page|Record $content, string $locale): ?string
    {
        $fingerprint = $this->fingerprint($content, $locale);

        if ($fingerprint !== null && is_file($current = $this->fileFor($content, $locale, $fingerprint))) {
            return $current;
        }

        return collect(File::glob($this->directory($content).'/'.$locale.'-*.png') ?: [])
            ->sortByDesc(fn (string $file): int => (int) @filemtime($file))
            ->first();
    }

    public function forget(Page|Record $content): void
    {
        File::deleteDirectory($this->directory($content));
    }

    /**
     * @param  array<string, mixed>  $card
     */
    private function capture(array $card): ?string
    {
        $source = config()->string('wireup.og_path').'/'.Str::uuid()->toString().'.html';

        File::ensureDirectoryExists(dirname($source));
        File::put($source, View::make('og.card', $card)->render());

        try {
            return $this->screenshot->capture('file://'.$source, '1200,630');
        } catch (Throwable) {
            return null;
        } finally {
            File::delete($source);
        }
    }

    private function fingerprint(Page|Record $content, string $locale): ?string
    {
        $title = $this->title($content, $locale);

        if ($title === '') {
            return null;
        }

        return mb_substr(hash('xxh128', serialize($this->card($title))), 0, 16);
    }

    private function title(Page|Record $content, string $locale): string
    {
        $translated = data_get($content->translationsFor('title'), $locale);

        return mb_trim(strip_tags((string) ($translated ?: $content->title)));
    }

    private function fileFor(Page|Record $content, string $locale, string $fingerprint): string
    {
        return $this->directory($content).'/'.$locale.'-'.$fingerprint.'.png';
    }

    private function directory(Page|Record $content): string
    {
        return config()->string('wireup.og_path').'/'.$this->key($content).'-'.$content->id;
    }

    private function key(Page|Record $content): string
    {
        return $content instanceof Page ? 'page' : 'record';
    }

    /**
     * @return array<string, mixed>
     */
    private function card(string $title): array
    {
        $settings = SettingsService::current();
        $colors = $settings->themeColors() ?: config()->array('theme.presets.default.colors');

        return [
            'title' => Str::limit($title, 110),
            'titleSize' => mb_strlen($title) > 60 ? 64 : 84,
            'brand' => $settings->brandName(),
            'logo' => $settings->logoUrl('header'),
            'background' => $this->color($colors, 'body_bg', '#ffffff'),
            'foreground' => $this->color($colors, 'body_text', '#18181b'),
            'accent' => $this->color($colors, 'accent', '#18181b'),
            'font' => $settings->fontFor('heading')['stack'] ?: 'system-ui, sans-serif',
        ];
    }

    /**
     * @param  array<string, mixed>  $colors
     */
    private function color(array $colors, string $slot, string $fallback): string
    {
        $value = $colors[$slot] ?? null;

        return is_string($value) && preg_match('/^#[0-9a-f]{3,8}$/i', $value) === 1 ? $value : $fallback;
    }
}
