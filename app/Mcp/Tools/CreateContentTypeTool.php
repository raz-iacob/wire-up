<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\CreateRecordTypeAction;
use App\Mcp\Support\Records;
use App\Models\RecordType;
use App\Models\Slug;
use App\Services\RecordTypePresets;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create-content-type')]
#[Description('Create a content type — a reusable blueprint (products, services, events, and so on) that records are made from. Start from a built-in preset or define custom fields. Returns the new type with its key, which list-records and create-record need.')]
final class CreateContentTypeTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            [
                'preset' => ['nullable', 'string'],
                'name' => ['nullable', 'string', 'max:255'],
                'slug_prefix' => ['nullable', 'string', 'lowercase', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::notIn(Records::reservedPrefixes())],
                'icon' => ['nullable', 'string', 'max:255'],
                ...Records::fieldRules(),
            ],
            [
                'name.max' => 'The name may not be longer than 255 characters.',
                'slug_prefix.regex' => 'The URL prefix may use lowercase letters, numbers and hyphens only.',
                'slug_prefix.not_in' => 'That URL prefix is reserved. Choose another.',
                ...Records::fieldMessages(),
            ],
        );

        $preset = null;

        if (($validated['preset'] ?? null) !== null) {
            $preset = RecordTypePresets::find($validated['preset']);

            if ($preset === null) {
                return Response::error("No preset \"{$validated['preset']}\". Available presets: ".implode(', ', RecordTypePresets::keys()).'.');
            }
        }

        $name = (string) ($validated['name'] ?? $preset['name'] ?? '');

        if ($name === '') {
            return Response::error('Pass a "name", or a "preset" to base the type on.');
        }

        $locale = resolve('localization')->getDefaultLocale();

        $fields = array_key_exists('fields', $validated)
            ? Records::serializeFields($validated['fields'], $locale)
            : ($preset['fields'] ?? []);

        $slugPrefix = (string) ($validated['slug_prefix'] ?? $preset['slug_prefix'] ?? Records::suggestSlugPrefix($name));

        if ($this->prefixTaken($slugPrefix)) {
            return Response::error("The URL prefix \"{$slugPrefix}\" is already in use. Pass a different \"slug_prefix\".");
        }

        $type = new CreateRecordTypeAction()->handle([
            'key' => $this->uniqueKey($preset['key'] ?? Str::slug($name, '_')),
            'name' => $name,
            'slug_prefix' => $slugPrefix,
            'icon' => (string) ($validated['icon'] ?? $preset['icon'] ?? 'rectangle-stack'),
            'fields' => $fields,
        ]);

        return Records::json([
            'content_type' => Records::typeSummary($type),
            'hint' => 'Add records to this type with create-record, passing its "key" as the type. Media fields (photo, gallery, and so on) are filled by passing media ids in create-record / update-record.',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'preset' => $schema->string()
                ->description('Optional built-in preset to base the type on (product, service, post, event, team-member, project, job). Its name, URL prefix, icon and fields are used unless you override them. See list-content-types for the available presets.'),

            'name' => $schema->string()
                ->description('The display name, e.g. "Products". Required unless a preset is given.'),

            'slug_prefix' => $schema->string()
                ->description('The URL prefix records live under, e.g. "products" → /products/{slug}. Lowercase, hyphenated. Derived from the name if omitted.'),

            'icon' => $schema->string()
                ->description('A Heroicon name for the admin sidebar, e.g. "shopping-bag". Defaults to "rectangle-stack".'),

            'fields' => $schema->array()
                ->items($schema->object())
                ->description('The custom field blueprint: [{"key": "sku", "type": "text", "label": "SKU", "required": false, "translatable": false, "column": false, "sortable": false, "searchable": false, "help": "", "options": [], "prefills": null}]. "type" is one of: text, textarea, rich-text, number, money, date, datetime, boolean, select, photo, video, audio, document, media-gallery, url. "translatable" defaults to the type default. "column"/"sortable"/"searchable" control the admin list. "options" is for select. "prefills" ("title" or "description") copies the field into the SEO title/description. Omit to use the preset fields.'),
        ];
    }

    private function prefixTaken(string $slugPrefix): bool
    {
        if (RecordType::query()->where('slug_prefix', $slugPrefix)->exists()) {
            return true;
        }

        return Slug::query()->where('slug', $slugPrefix)->where('base_path', '')->exists();
    }

    private function uniqueKey(string $base): string
    {
        $base = $base !== '' ? $base : 'type';
        $key = $base;
        $counter = 1;

        while (RecordType::query()->where('key', $key)->exists()) {
            $key = "{$base}_{$counter}";
            $counter++;
        }

        return $key;
    }
}
