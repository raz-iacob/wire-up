<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Ai\Contracts\RequiresConfirmation;
use App\Mcp\Support\ContentSearch;
use App\Mcp\Support\Pages;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('replace-text')]
#[Description('Replace text across every page and record in one call — a renamed product, a changed phone number, a corrected spelling. It changes the same matches search-content lists for the same arguments, so run that first. Only visible text changes, never HTML tags, links or media. Every changed page and record gets a new revision, so History can restore it.')]
final class ReplaceTextTool extends Tool implements RequiresConfirmation
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            [...ContentSearch::rules(), 'replace' => ['present', 'nullable', 'string', 'max:2000']],
            [
                ...ContentSearch::messages(),
                'replace.present' => 'Pass "replace": the new text. Use "" to delete the matches.',
                'replace.string' => '"replace" must be text.',
                'replace.max' => '"replace" may not be longer than 2000 characters.',
            ],
        );

        $find = (string) $validated['find'];
        $replacement = (string) ($validated['replace'] ?? '');
        $ignoreCase = (bool) ($validated['ignore_case'] ?? false);

        if ($find === $replacement) {
            return Response::error('"find" and "replace" are the same, so there is nothing to change.');
        }

        $changed = [];
        $total = 0;

        foreach (ContentSearch::models($validated) as $model) {
            $count = ContentSearch::replaceIn($model, $find, $replacement, $ignoreCase, $validated['locale'] ?? null);

            if ($count > 0) {
                $changed[] = [...ContentSearch::describe($model->refresh()), 'replacements' => $count];
                $total += $count;
            }
        }

        return Pages::json([
            'replaced' => $total,
            'changed' => $changed,
            'hint' => $total === 0
                ? 'Nothing matched, so nothing changed. Check the text with search-content.'
                : 'Each changed page and record has a new revision; restore it from History in the admin to undo.',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'find' => $schema->string()
                ->description('The text to replace, at least 2 characters.')
                ->required(),

            'replace' => $schema->string()
                ->description('The new text. Use "" to delete the matches.')
                ->required(),

            'ignore_case' => $schema->boolean()
                ->description('true matches regardless of upper and lower case. Defaults to false.'),

            'locale' => $schema->string()
                ->description('Only change this language, e.g. "en". Leave it out to change every language.'),

            'pages' => $schema->array()
                ->items($schema->integer())
                ->description('Only change these page ids. If you pass "pages" or "records", only the ids you list change.'),

            'records' => $schema->array()
                ->items($schema->integer())
                ->description('Only change these record ids.'),
        ];
    }
}
