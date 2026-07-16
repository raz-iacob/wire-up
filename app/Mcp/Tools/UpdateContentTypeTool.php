<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\UpdateRecordTypeAction;
use App\Mcp\Support\Records;
use App\Models\RecordType;
use App\Models\Slug;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('update-content-type')]
#[Description('Update a content type: rename it, change its icon or URL prefix, or replace its field blueprint. Passing "fields" replaces the whole blueprint — include every field the type should keep. Omitted attributes are left unchanged.')]
final class UpdateContentTypeTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            [
                'type' => ['required', 'string'],
                'name' => ['nullable', 'string', 'max:255'],
                'slug_prefix' => ['nullable', 'string', 'lowercase', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::notIn(Records::reservedPrefixes())],
                'icon' => ['nullable', 'string', 'max:255'],
                ...Records::fieldRules(),
            ],
            [
                'type.required' => 'Pass the content type key. Use list-content-types to find it.',
                'name.max' => 'The name may not be longer than 255 characters.',
                'slug_prefix.regex' => 'The URL prefix may use lowercase letters, numbers and hyphens only.',
                'slug_prefix.not_in' => 'That URL prefix is reserved. Choose another.',
                ...Records::fieldMessages(),
            ],
        );

        $type = RecordType::query()->where('key', $validated['type'])->first();

        if ($type === null) {
            return Response::error("No content type with key \"{$validated['type']}\". Use list-content-types to see the available types.");
        }

        $attributes = [];

        if (($validated['name'] ?? null) !== null) {
            $attributes['name'] = $validated['name'];
        }

        if (($validated['icon'] ?? null) !== null) {
            $attributes['icon'] = $validated['icon'];
        }

        if (($validated['slug_prefix'] ?? null) !== null) {
            if ($this->prefixTaken($validated['slug_prefix'], $type)) {
                return Response::error("The URL prefix \"{$validated['slug_prefix']}\" is already in use. Choose a different one.");
            }

            $attributes['slug_prefix'] = $validated['slug_prefix'];
        }

        if (array_key_exists('fields', $validated)) {
            $attributes['fields'] = Records::serializeFields($validated['fields'], resolve('localization')->getDefaultLocale());
        }

        if ($attributes !== []) {
            new UpdateRecordTypeAction()->handle($type, $attributes);
        }

        return Records::json(['content_type' => Records::typeSummary($type->refresh())]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->description('The content type key, as returned by list-content-types or create-content-type.')
                ->required(),

            'name' => $schema->string()
                ->description('A new display name.'),

            'slug_prefix' => $schema->string()
                ->description('A new URL prefix (lowercase, hyphenated). Changing it changes every record URL under this type.'),

            'icon' => $schema->string()
                ->description('A new Heroicon name for the admin sidebar.'),

            'fields' => $schema->array()
                ->items($schema->object())
                ->description('The complete replacement field blueprint (same shape as create-content-type). Include every field to keep — any field left out is removed from the blueprint. Omit this key entirely to leave the fields unchanged.'),
        ];
    }

    private function prefixTaken(string $slugPrefix, RecordType $type): bool
    {
        if (RecordType::query()->where('slug_prefix', $slugPrefix)->whereKeyNot($type->id)->exists()) {
            return true;
        }

        return Slug::query()->where('slug', $slugPrefix)->where('base_path', '')->exists();
    }
}
