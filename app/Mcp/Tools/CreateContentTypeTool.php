<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\CreateRecordTypeAction;
use App\Mcp\Support\Records;
use App\Models\RecordType;
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
                'breadcrumbs' => ['nullable', 'boolean'],
                'has_detail_page' => ['nullable', 'boolean'],
                'has_index_page' => ['nullable', 'boolean'],
                'sellable' => ['nullable', 'boolean'],
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

        $sellable = (bool) ($validated['sellable'] ?? $preset['sellable'] ?? false);

        if ($sellable) {
            $fields = RecordTypePresets::withSellableFields($fields);
        }

        $slugPrefix = (string) ($validated['slug_prefix'] ?? $preset['slug_prefix'] ?? Records::suggestSlugPrefix($name));

        if ($this->prefixTaken($slugPrefix)) {
            return Response::error("The URL prefix \"{$slugPrefix}\" is already in use. Pass a different \"slug_prefix\".");
        }

        $type = new CreateRecordTypeAction()->handle([
            'key' => $this->uniqueKey($preset['key'] ?? Str::slug($name, '_')),
            'name' => $name,
            'slug_prefix' => $slugPrefix,
            'icon' => (string) ($validated['icon'] ?? $preset['icon'] ?? 'rectangle-stack'),
            'breadcrumbs' => (bool) ($validated['breadcrumbs'] ?? false),
            'has_detail_page' => (bool) ($validated['has_detail_page'] ?? true),
            'has_index_page' => (bool) ($validated['has_index_page'] ?? false),
            'sellable' => $sellable,
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

            'breadcrumbs' => $schema->boolean()
                ->description('Show the breadcrumb trail back to the home page on every record page of this type. Defaults to false.'),

            'has_detail_page' => $schema->boolean()
                ->description('Give each record its own page at /{prefix}/{slug}. Defaults to true. Set false when the records only feed cards: the detail route 404s, cards render unlinked, and the records leave the sitemap and site search.'),

            'has_index_page' => $schema->boolean()
                ->description('Publish a listing of every published record of this type at /{prefix}. Defaults to false. A page whose web address matches the prefix takes precedence.'),

            'sellable' => $schema->boolean()
                ->description('Sell the records of this type: adds current_price (money), billing (select: One-time, Monthly, Yearly) and shippable (boolean) fields if missing, and shows a buy button on each record once Stripe is connected. Defaults to true for the product preset, false otherwise.'),

            'fields' => $schema->array()
                ->items($schema->object())
                ->description('The custom field blueprint: [{"key": "sku", "type": "text", "label": "SKU", "required": false, "translatable": false, "column": false, "sortable": false, "searchable": false, "help": "", "options": [], "prefills": null}]. "type" is one of: text, textarea, rich-text, number, money, date, datetime, boolean, select, photo, video, audio, document, media-gallery, url. "translatable" defaults to the type default. "column"/"sortable"/"searchable" control the admin list. "options" is for select. "prefills" ("title" or "description") copies the field into the SEO title/description. Omit to use the preset fields.'),
        ];
    }

    private function prefixTaken(string $slugPrefix): bool
    {
        return RecordType::query()->where('slug_prefix', $slugPrefix)->exists();
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
