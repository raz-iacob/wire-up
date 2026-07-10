<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use App\Services\ContentMarkdown;
use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class MarkdownForAgents
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true) || ! $this->prefersMarkdown($request)) {
            return $next($request)->setVary('Accept', false);
        }

        $content = $this->resolveContent($request);

        abort_if($content === null, 404);

        return response(ContentMarkdown::current()->render($content), 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
        ])->setVary('Accept');
    }

    private function prefersMarkdown(Request $request): bool
    {
        return $request->prefers(['text/html', 'text/markdown']) === 'text/markdown';
    }

    private function resolveContent(Request $request): Page|Record|null
    {
        return match ($request->route()?->getName()) {
            'home' => Page::query()
                ->with('blocks')
                ->whereKey(SettingsService::current()->homePageId())
                ->publishedInLocale()
                ->first(),
            'page' => Page::query()
                ->with('blocks')
                ->forSlug((string) $request->route('slug'))
                ->publishedInLocale()
                ->first(),
            default => $this->resolveRecord((string) $request->route('recordType'), (string) $request->route('slug')),
        };
    }

    private function resolveRecord(string $slugPrefix, string $slug): ?Record
    {
        $type = RecordType::query()->where('slug_prefix', $slugPrefix)->first();

        if ($type === null) {
            return null;
        }

        return Record::query()
            ->where('record_type_id', $type->id)
            ->with(['recordType', 'blocks', 'media', 'translations', 'slugs', 'categories'])
            ->forSlug($slug, null, $type->slug_prefix)
            ->publishedInLocale()
            ->first();
    }
}
