<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RedirectOrigin;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use App\Models\Slug;
use App\Services\RedirectService;

final readonly class RedirectMovedContentAction
{
    /**
     * @param  array<string, string>  $previousPaths
     */
    public function handle(Page|Record $content, array $previousPaths): void
    {
        foreach ($content->slugs()->get() as $slug) {
            $this->follow($previousPaths[$slug->locale] ?? null, $slug->publicPath());
        }
    }

    /**
     * @return array<string, string>
     */
    public function livePaths(Page|Record $content): array
    {
        if ($content instanceof Page ? $content->isHomePage() : ! $content->recordType->has_detail_page) {
            return [];
        }

        return $content->slugs()->get()
            ->filter(fn (Slug $slug): bool => $slug->slug !== '' && $content->isLiveInLocale($slug->locale))
            ->mapWithKeys(fn (Slug $slug): array => [$slug->locale => $slug->publicPath()])
            ->all();
    }

    public function handlePrefix(RecordType $recordType, string $previousPrefix): void
    {
        $redirects = resolve(RedirectService::class);

        $redirects->releasePrefix($recordType->slug_prefix);
        $redirects->release(Slug::query()
            ->where('sluggable_type', new Record()->getMorphClass())
            ->whereIn('sluggable_id', $recordType->records()->select('id'))
            ->get()
            ->map(fn (Slug $slug): string => $slug->publicPath())
            ->all());

        foreach (resolve('localization')->getActiveLocaleCodes() as $locale) {
            $from = RedirectService::localizedPath($locale, $previousPrefix);
            $to = RedirectService::localizedPath($locale, $recordType->slug_prefix);

            if ($recordType->has_detail_page) {
                $this->follow($from.'/*', $to.'/*');
            }

            if ($recordType->has_index_page) {
                $this->follow($from, $to);
            }
        }
    }

    private function follow(?string $from, string $to): void
    {
        $save = new SaveRedirectAction();

        if ($from !== null && $from !== $to && $save->problems($from, $to) === []) {
            $save->handle($from, $to, 301, RedirectOrigin::AUTOMATIC);
        }
    }
}
