<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Support\ContentSearch;
use App\Mcp\Support\Pages;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('search-content')]
#[Description('Find text across every page and record: titles, descriptions, block text in every language and text fields. Returns where each match is — the page or record, the block key, the language and a short excerpt. Matching is case-sensitive unless "ignore_case" is true, and looks at visible text only, never inside HTML tags, links or media. Call replace-text with the same arguments to change exactly these matches.')]
final class SearchContentTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            [...ContentSearch::rules(), 'limit' => ['integer', 'min:1', 'max:200']],
            [...ContentSearch::messages(), 'limit.*' => '"limit" must be a whole number from 1 to 200.'],
        );

        $find = (string) $validated['find'];
        $ignoreCase = (bool) ($validated['ignore_case'] ?? false);
        $locale = $validated['locale'] ?? null;
        $limit = (int) ($validated['limit'] ?? 50);

        $results = [];
        $matches = 0;
        $places = 0;

        foreach (ContentSearch::models($validated) as $model) {
            $hits = [];

            foreach (ContentSearch::strings($model) as $string) {
                $count = $locale === null || $string['locale'] === $locale ? ContentSearch::count($string['text'], $find, $ignoreCase) : 0;

                if ($count === 0) {
                    continue;
                }

                $matches += $count;

                if (++$places <= $limit) {
                    $hits[] = [
                        'key' => $string['key'],
                        'locale' => $string['locale'],
                        'count' => $count,
                        'excerpt' => ContentSearch::excerpt($string['text'], $find, $ignoreCase),
                    ];
                }
            }

            if ($hits !== []) {
                $results[] = [...ContentSearch::describe($model), 'hits' => $hits];
            }
        }

        return Pages::json([
            'find' => $find,
            'matches' => $matches,
            'places' => $places,
            'truncated' => $places > $limit,
            'results' => $results,
            'hint' => $matches === 0
                ? 'Nothing matched. Matching is case-sensitive unless "ignore_case" is true.'
                : 'Change every match with replace-text using the same find, ignore_case, locale, pages and records, or one block with edit-blocks. This covers pages and records only: the site title, menus and interface text change with update-identity, update-menu and update-interface-translations.',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'find' => $schema->string()
                ->description('The text to look for, at least 2 characters.')
                ->required(),

            'ignore_case' => $schema->boolean()
                ->description('true matches regardless of upper and lower case. Defaults to false.'),

            'locale' => $schema->string()
                ->description('Only look in this language, e.g. "en". Leave it out to search every language.'),

            'pages' => $schema->array()
                ->items($schema->integer())
                ->description('Only search these page ids. If you pass "pages" or "records", only the ids you list are searched.'),

            'records' => $schema->array()
                ->items($schema->integer())
                ->description('Only search these record ids.'),

            'limit' => $schema->integer()
                ->description('How many places to list, 1 to 200. Defaults to 50; "matches" and "places" still count everything.'),
        ];
    }
}
