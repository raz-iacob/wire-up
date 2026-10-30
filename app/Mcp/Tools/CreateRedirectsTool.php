<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\ImportRedirectsAction;
use App\Enums\RedirectOrigin;
use App\Mcp\Support\Pages;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create-redirects')]
#[Description('Add or update up to 500 redirects in one call, so old addresses keep working after a site is rebuilt or reorganised. Each item sends an old path on this site to a new path or a full https:// address. A redirect with the same old address is updated rather than duplicated. Items that would loop, point at themselves or touch the admin are skipped with a reason.')]
final class CreateRedirectsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            [
                'redirects' => ['required', 'array', 'min:1', 'max:500'],
                'redirects.*.from' => ['required', 'string', 'max:500'],
                'redirects.*.to' => ['required', 'string', 'max:2048'],
                'redirects.*.status' => ['sometimes', 'integer', 'in:301,302'],
            ],
            [
                'redirects.required' => 'Pass the redirects to add, such as [{"from": "/old-page", "to": "/new-page"}].',
                'redirects.max' => 'Add at most 500 redirects per call.',
                'redirects.*.from.required' => 'Every redirect needs a "from" path.',
                'redirects.*.to.required' => 'Every redirect needs a "to" path or address.',
                'redirects.*.status.in' => 'status must be 301 (permanent) or 302 (temporary).',
            ],
        );

        /** @var array<int, array{from: string, to: string, status?: int}> $items */
        $items = array_values($validated['redirects']);

        $rows = [];
        foreach ($items as $index => $item) {
            $rows[$index + 1] = ['from' => $item['from'], 'to' => $item['to'], 'status' => isset($item['status']) ? (int) $item['status'] : null];
        }

        $summary = resolve(ImportRedirectsAction::class)->handle($rows, RedirectOrigin::MANUAL);

        return Pages::json([
            'added' => $summary['added'],
            'updated' => $summary['updated'],
            'skipped' => array_map(fn (array $skipped): array => ['item' => $skipped['row'], 'from' => $skipped['from'], 'reason' => $skipped['reason']], $summary['skipped']),
            'hint' => 'Check them with list-redirects, or fetch an old address to confirm it now redirects.',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'redirects' => $schema->array()
                ->items($schema->object())
                ->description('The redirects to add: [{"from": "/old-page", "to": "/new-page", "status": 301}]. "from" is a path on this site (a query string such as /page?id=3 is matched exactly); "to" is a path here or a full https:// address. End both with /* to move a whole folder: /blog/* to /news/* sends /blog/hello to /news/hello. "status" is 301 (permanent, the default) or 302 (temporary).')
                ->required(),
        ];
    }
}
