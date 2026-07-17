<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\UpdatePageAction;
use App\Actions\UpdateRecordAction;
use App\Mcp\Support\ContentStrings;
use App\Mcp\Support\Pages;
use App\Models\Record;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('update-content-strings')]
#[Description('Save translations for a page or record into a target language. Pass a map of the keys from get-content-strings to their translated text. Other languages are never overwritten; the translated language is published automatically (its slug is generated from the translated title).')]
final class UpdateContentStringsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $targetLocales = ContentStrings::targetLocales();

        $validated = $request->validate(
            [
                'type' => ['required', 'string', 'in:page,record'],
                'id' => ['required', 'integer'],
                'locale' => ['required', 'string', Rule::in($targetLocales)],
                'translations' => ['required', 'array'],
            ],
            [
                'type.required' => 'Pass "type": "page" or "record".',
                'type.in' => 'Type must be "page" or "record".',
                'id.required' => 'Pass the "id" of the page or record.',
                'locale.required' => 'Pass the "locale" to translate into.',
                'locale.in' => $targetLocales === []
                    ? 'There are no non-default languages active to translate into. Enable another language first.'
                    : 'Unknown or non-translatable locale. Translate into one of: '.implode(', ', $targetLocales).'.',
                'translations.required' => 'Pass "translations" as a map of key (from get-content-strings) to its translation.',
                'translations.array' => 'Pass "translations" as a map of key to its translation.',
            ],
        );

        $model = ContentStrings::resolve($validated['type'], $validated['id']);

        if ($model === null) {
            return Response::error("No {$validated['type']} with id {$validated['id']}. Use list-{$validated['type']}s to find it.");
        }

        $result = ContentStrings::apply($model, $validated['locale'], $validated['translations']);

        if ($result['applied'] > 0) {
            match (true) {
                $model instanceof Record => new UpdateRecordAction()->handle($model, $result['attributes']),
                default => new UpdatePageAction()->handle($model, $result['attributes']),
            };
        }

        $model->refresh();

        $remaining = count(array_filter(
            ContentStrings::extract($model, $validated['locale']),
            fn (array $string): bool => $string['current'] === '',
        ));

        return Pages::json([
            'type' => $validated['type'],
            'id' => $validated['id'],
            'locale' => $validated['locale'],
            'applied' => $result['applied'],
            'unknown' => $result['unknown'],
            'published' => $result['published'],
            'remaining' => $remaining,
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
                ->description('The page or record id.')
                ->required(),

            'locale' => $schema->string()
                ->description('The language code to translate into.')
                ->required(),

            'translations' => $schema->object()
                ->description('A map of key (exactly as returned by get-content-strings) to the translated text, e.g. {"title": "Over ons", "blocks.42.heading": "<p>Welkom</p>"}. Unknown keys are reported back.')
                ->required(),
        ];
    }
}
