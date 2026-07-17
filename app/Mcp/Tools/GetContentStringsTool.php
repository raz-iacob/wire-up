<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Support\ContentStrings;
use App\Mcp\Support\Pages;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get-content-strings')]
#[Description('List every translatable string on a page or record — its title, description, block text and (for records) field values — each with a stable key, the source-language text, and the current translation for the target language. Use this before update-content-strings.')]
final class GetContentStringsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $targetLocales = ContentStrings::targetLocales();

        $validated = $request->validate(
            [
                'type' => ['required', 'string', 'in:page,record'],
                'id' => ['required', 'integer'],
                'locale' => ['required', 'string', Rule::in($targetLocales)],
            ],
            [
                'type.required' => 'Pass "type": "page" or "record".',
                'type.in' => 'Type must be "page" or "record".',
                'id.required' => 'Pass the "id" of the page or record. Use list-pages or list-records to find it.',
                'locale.required' => 'Pass the "locale" to translate into.',
                'locale.in' => $targetLocales === []
                    ? 'There are no non-default languages active to translate into. Enable another language first.'
                    : 'Unknown or non-translatable locale. Translate into one of: '.implode(', ', $targetLocales).'.',
            ],
        );

        $model = ContentStrings::resolve($validated['type'], $validated['id']);

        if ($model === null) {
            return Response::error("No {$validated['type']} with id {$validated['id']}. Use list-{$validated['type']}s to find it.");
        }

        return Pages::json([
            'type' => $validated['type'],
            'id' => $validated['id'],
            'locale' => $validated['locale'],
            'source_locale' => resolve('localization')->getDefaultLocale(),
            'strings' => ContentStrings::extract($model, $validated['locale']),
            'hint' => 'Translate each "source" and save it under the same "key" with update-content-strings. The translated locale is published automatically once its title is set.',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->enum(['page', 'record'])
                ->description('Whether the content is a "page" or a "record".')
                ->required(),

            'id' => $schema->integer()
                ->description('The page or record id, from list-pages / list-records.')
                ->required(),

            'locale' => $schema->string()
                ->description('The language code to translate into (any active language other than the default).')
                ->required(),
        ];
    }
}
