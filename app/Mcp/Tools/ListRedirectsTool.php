<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Support\Pages;
use App\Models\NotFoundPath;
use App\Models\Redirect;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('list-redirects')]
#[Description('List the site\'s redirects (newest first) with how often each was used, plus the most requested addresses that do not exist. Use it to see which old URLs still need a redirect, then add them with create-redirects.')]
final class ListRedirectsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            [
                'search' => ['sometimes', 'nullable', 'string', 'max:255'],
                'limit' => ['sometimes', 'integer', 'min:1', 'max:500'],
            ],
            ['limit.max' => 'List at most 500 redirects at a time.'],
        );

        $search = (string) ($validated['search'] ?? '');

        $redirects = Redirect::query()
            ->when($search !== '', fn (Builder $query): Builder => $query->where(
                fn (Builder $query): Builder => $query->whereLike('from', "%{$search}%")->orWhereLike('to', "%{$search}%"),
            ))
            ->latest('id')
            ->limit((int) ($validated['limit'] ?? 100))
            ->get()
            ->map(fn (Redirect $redirect): array => [
                'id' => $redirect->id,
                'from' => $redirect->from,
                'to' => $redirect->to,
                'status' => $redirect->status_code,
                'origin' => $redirect->origin->value,
                'hits' => $redirect->hits,
                'last_hit_at' => $redirect->last_hit_at?->toIso8601String(),
            ]);

        $notFound = NotFoundPath::query()
            ->orderByDesc('hits')
            ->limit(25)
            ->get()
            ->map(fn (NotFoundPath $path): array => ['path' => $path->path, 'hits' => $path->hits, 'last_seen_at' => $path->last_seen_at->toIso8601String()]);

        return Pages::json([
            'redirects' => $redirects->all(),
            'not_found' => $notFound->all(),
            'hint' => 'Wildcard redirects end in /* and carry the rest of the address over. Changing a page or record address adds an automatic redirect by itself.',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()
                ->description('Only return redirects whose old or new address contains this text.'),

            'limit' => $schema->integer()
                ->description('Maximum number of redirects to return (default 100, max 500).')
                ->default(100),
        ];
    }
}
