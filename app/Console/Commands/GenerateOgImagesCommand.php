<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Page;
use App\Models\Record;
use App\Services\OgImageService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Generate the fallback share images for every published page and record')]
#[Signature('wireup:og:generate {--force : Rebuild images that are already current}')]
final class GenerateOgImagesCommand extends Command
{
    public function handle(OgImageService $images): int
    {
        $made = 0;
        $skipped = 0;

        foreach ([Page::query()->with('translations'), Record::query()->with('translations')] as $query) {
            foreach ($query->lazyById() as $content) {
                foreach ($content->published_locales as $locale) {
                    if ($this->option('force')) {
                        $images->forget($content);
                    }

                    $images->generate($content, (string) $locale) ? $made++ : $skipped++;
                }
            }
        }

        $this->components->info("Generated or confirmed {$made} share images.");

        if ($skipped > 0) {
            $this->components->warn("Skipped {$skipped} without a usable title, or because no headless browser is available.");
        }

        return self::SUCCESS;
    }
}
