<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Page;
use App\Models\Record;
use App\Services\OgImageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Middleware\WithoutOverlapping;

#[Timeout(120)]
#[Tries(1)]
final class GenerateOgImage implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $type,
        public readonly int $id,
    ) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new WithoutOverlapping($this->type.'-'.$this->id)];
    }

    public function handle(OgImageService $images): void
    {
        $content = $this->type === 'page'
            ? Page::query()->with('translations')->find($this->id)
            : Record::query()->with('translations')->find($this->id);

        if (! $content instanceof Page && ! $content instanceof Record) {
            return;
        }

        foreach ($content->published_locales as $locale) {
            $images->generate($content, (string) $locale);
        }
    }
}
