<?php

declare(strict_types=1);

use App\Actions\CreateRecordTypeAction;
use App\Actions\DeleteRecordTypeAction;
use App\Actions\UpdateRecordTypeAction;
use App\Enums\FieldType;
use App\Models\RecordType;
use App\Services\RecordTypePresets;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

return new class extends Component
{
    /**
     * @var array<int, array<string, mixed>>
     */
    public array $types = [];

    /**
     * @var array<int, int>
     */
    public array $originalIds = [];

    public int $seq = 0;

    public ?string $removeKey = null;

    public function mount(): void
    {
        $locale = resolve('localization')->getDefaultLocale();

        $this->types = RecordType::query()
            ->orderBy('position')
            ->get()
            ->map(fn (RecordType $type): array => $this->hydrateType($type, $locale))
            ->all();

        $this->originalIds = $this->currentIds();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function availablePresets(): array
    {
        $usedKeys = array_column($this->types, 'key');
        $usedPrefixes = array_column($this->types, 'slug_prefix');

        return array_values(array_filter(
            RecordTypePresets::all(),
            fn (array $preset): bool => ! in_array($preset['key'], $usedKeys, true)
                && ! in_array($preset['slug_prefix'], $usedPrefixes, true),
        ));
    }

    public function addPreset(string $key): void
    {
        $preset = RecordTypePresets::find($key);

        if ($preset === null) {
            return;
        }

        $locale = resolve('localization')->getDefaultLocale();

        $this->types[] = [
            '_key' => (string) $this->seq++,
            'id' => null,
            'key' => $preset['key'],
            'name' => $preset['name'],
            'slug_prefix' => $preset['slug_prefix'],
            'icon' => $preset['icon'],
            'fields' => array_map(fn (array $field): array => $this->hydrateField($field, $locale), $preset['fields']),
            'open' => true,
        ];
    }

    public function addCustom(): void
    {
        $this->types[] = [
            '_key' => (string) $this->seq++,
            'id' => null,
            'key' => '',
            'name' => '',
            'slug_prefix' => '',
            'icon' => 'rectangle-stack',
            'fields' => [],
            'open' => true,
        ];
    }

    public function addField(string $typeKey, string $fieldType): void
    {
        $index = $this->typeIndex($typeKey);
        $type = FieldType::tryFrom($fieldType);

        if ($index === null || $type === null) {
            return;
        }

        $this->types[$index]['fields'][] = [
            '_key' => (string) $this->seq++,
            'key' => $this->uniqueFieldKey($index),
            'type' => $type->value,
            'label' => '',
            'required' => false,
            'translatable' => $type->isTranslatableByDefault(),
            'column' => false,
            'sortable' => false,
            'searchable' => false,
            'help' => '',
            'options' => '',
            'open' => true,
        ];
    }

    public function removeField(string $fieldKey): void
    {
        foreach ($this->types as $i => $type) {
            foreach ($type['fields'] as $j => $field) {
                if ($field['_key'] === $fieldKey) {
                    unset($this->types[$i]['fields'][$j]);
                    $this->types[$i]['fields'] = array_values($this->types[$i]['fields']);

                    return;
                }
            }
        }
    }

    public function reorderFields(string $fieldKey, int $position): void
    {
        foreach ($this->types as $i => $type) {
            $from = array_search($fieldKey, array_column($type['fields'], '_key'), true);

            if ($from === false) {
                continue;
            }

            $fields = $type['fields'];
            $moved = $fields[$from];
            array_splice($fields, $from, 1);
            array_splice($fields, max(0, min($position, count($fields))), 0, [$moved]);
            $this->types[$i]['fields'] = $fields;

            return;
        }
    }

    public function reorderTypes(string $typeKey, int $position): void
    {
        $from = array_search($typeKey, array_column($this->types, '_key'), true);

        if ($from === false) {
            return;
        }

        $moved = $this->types[$from];
        array_splice($this->types, $from, 1);
        array_splice($this->types, max(0, min($position, count($this->types))), 0, [$moved]);
    }

    public function update(CreateRecordTypeAction $create, UpdateRecordTypeAction $update, DeleteRecordTypeAction $delete): void
    {
        if ($this->types !== []) {
            try {
                $this->validate($this->rules(), $this->messages(), $this->validationAttributes());
                $this->assertUniquePrefixes();
            } catch (ValidationException $e) {
                $this->revealErrors($e);

                throw $e;
            }
        }

        $locale = resolve('localization')->getDefaultLocale();

        DB::transaction(function () use ($create, $update, $delete, $locale): void {
            foreach (array_diff($this->originalIds, $this->currentIds()) as $id) {
                $delete->handle(RecordType::query()->findOrFail($id));
            }

            foreach ($this->types as $position => $row) {
                $attributes = [
                    'slug_prefix' => $row['slug_prefix'],
                    'icon' => $row['icon'],
                    'name' => $row['name'],
                    'fields' => $this->serializeFields($row['fields'], $locale),
                    'position' => $position,
                ];

                if ($row['id'] === null) {
                    $base = $row['key'] !== '' ? $row['key'] : Str::slug($row['name'], '_');
                    $type = $create->handle([...$attributes, 'key' => $this->uniqueKey($base)]);
                    $this->types[$position]['id'] = $type->id;
                    $this->types[$position]['key'] = $type->key;
                } else {
                    $update->handle(RecordType::query()->findOrFail($row['id']), $attributes);
                }
            }
        });

        $this->originalIds = $this->currentIds();

        Flux::toast(__('Content types updated.'), variant: 'success');
    }

    public function confirmRemove(string $typeKey): void
    {
        $this->removeKey = $typeKey;
        Flux::modal('confirm-delete-type')->show();
    }

    public function removeType(): void
    {
        $index = $this->typeIndex((string) $this->removeKey);

        if ($index !== null) {
            unset($this->types[$index]);
            $this->types = array_values($this->types);
        }

        $this->removeKey = null;
        Flux::modal('confirm-delete-type')->close();
    }

    /**
     * @return array<int, int>
     */
    private function currentIds(): array
    {
        return array_values(array_filter(array_map(
            fn (array $type): ?int => $type['id'],
            $this->types,
        )));
    }

    private function assertUniquePrefixes(): void
    {
        $counts = array_count_values(array_map(fn (array $type): string => $type['slug_prefix'], $this->types));

        $messages = [];

        foreach ($this->types as $index => $type) {
            if (($counts[$type['slug_prefix']] ?? 0) > 1) {
                $messages["types.$index.slug_prefix"] = __('Each content type needs a unique URL prefix.');
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    private function revealErrors(ValidationException $e): void
    {
        $keys = [];

        foreach (array_keys($e->errors()) as $errorKey) {
            $segments = explode('.', (string) $errorKey);

            if ($segments[0] !== 'types') {
                continue;
            }

            $type = $this->types[(int) ($segments[1] ?? -1)] ?? null;

            if (! is_array($type)) {
                continue;
            }

            $keys[] = $type['_key'];

            if (($segments[2] ?? null) === 'fields' && isset($segments[3])) {
                $field = $type['fields'][(int) $segments[3]] ?? null;

                if (is_array($field)) {
                    $keys[] = $field['_key'];
                }
            }
        }

        $this->dispatch('record-type-errors', keys: array_values(array_unique($keys)));
    }

    /**
     * @return array<string, mixed>
     */
    private function hydrateType(RecordType $type, string $locale): array
    {
        return [
            '_key' => (string) $this->seq++,
            'id' => $type->id,
            'key' => $type->key,
            'name' => $type->name,
            'slug_prefix' => $type->slug_prefix,
            'icon' => $type->icon,
            'fields' => array_map(fn (array $field): array => $this->hydrateField($field, $locale), $type->fields),
            'open' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    private function hydrateField(array $field, string $locale): array
    {
        $label = $field['label'] ?? [];

        return [
            '_key' => (string) $this->seq++,
            'key' => $field['key'] ?? '',
            'type' => $field['type'] ?? FieldType::TEXT->value,
            'label' => is_array($label) ? ($label[$locale] ?? (string) Arr::first($label, default: '')) : (string) $label,
            'required' => (bool) ($field['required'] ?? false),
            'translatable' => (bool) ($field['translatable'] ?? false),
            'column' => (bool) ($field['column'] ?? false),
            'sortable' => (bool) ($field['sortable'] ?? false),
            'searchable' => (bool) ($field['searchable'] ?? false),
            'help' => $field['help'] ?? '',
            'options' => implode("\n", $field['options'] ?? []),
            'open' => false,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, array<string, mixed>>
     */
    private function serializeFields(array $fields, string $locale): array
    {
        return array_map(fn (array $field): array => [
            'key' => $field['key'],
            'type' => $field['type'],
            'label' => [$locale => $field['label']],
            'required' => (bool) ($field['required'] ?? false),
            'translatable' => (bool) ($field['translatable'] ?? false),
            'column' => (bool) ($field['column'] ?? false),
            'sortable' => (bool) ($field['sortable'] ?? false),
            'searchable' => (bool) ($field['searchable'] ?? false),
            'help' => (string) ($field['help'] ?? ''),
            'options' => $this->parseOptions((string) ($field['options'] ?? '')),
        ], $fields);
    }

    /**
     * @return array<int, string>
     */
    private function parseOptions(string $value): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $value) ?: [])
            ->map(fn (string $line): string => mb_trim($line))
            ->filter(fn (string $line): bool => $line !== '')
            ->values()
            ->all();
    }

    private function typeIndex(string $typeKey): ?int
    {
        $index = array_search($typeKey, array_column($this->types, '_key'), true);

        return $index === false ? null : $index;
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        $reserved = array_merge(
            config()->array('records.reserved_prefixes'),
            resolve('localization')->getActiveLocaleCodes()->all(),
        );

        $rules = [];

        foreach (array_keys($this->types) as $index) {
            $rules["types.$index.name"] = ['required', 'string', 'max:255'];
            $rules["types.$index.slug_prefix"] = [
                'required', 'string', 'lowercase', 'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::notIn($reserved),
                Rule::unique('record_types', 'slug_prefix')->ignore($this->types[$index]['id']),
                Rule::unique('slugs', 'slug')->where('base_path', ''),
            ];
            $rules["types.$index.fields"] = ['array'];
            $rules["types.$index.fields.*.key"] = [
                'required', 'string', 'distinct', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::notIn(config()->array('records.reserved_field_keys')),
            ];
            $rules["types.$index.fields.*.label"] = ['required', 'string', 'max:255'];
            $rules["types.$index.fields.*.type"] = ['required', Rule::in(FieldType::values())];
            $rules["types.$index.fields.*.help"] = ['nullable', 'string', 'max:500'];
            $rules["types.$index.fields.*.options"] = ['nullable', 'string'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'types.*.slug_prefix.not_in' => __('That URL prefix is reserved. Choose another.'),
            'types.*.slug_prefix.unique' => __('That URL prefix is already in use.'),
            'types.*.slug_prefix.regex' => __('Use lowercase letters, numbers and hyphens only.'),
            'types.*.fields.*.key.regex' => __('Field keys must start with a letter and use lowercase letters, numbers and underscores.'),
            'types.*.fields.*.key.distinct' => __('Each field needs a unique key.'),
            'types.*.fields.*.key.not_in' => __('That field key is reserved. Choose another.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function validationAttributes(): array
    {
        $attributes = [];

        foreach ($this->types as $index => $type) {
            $attributes["types.$index.name"] = __('name');
            $attributes["types.$index.slug_prefix"] = __('URL prefix');

            foreach ($type['fields'] as $fieldIndex => $field) {
                $label = $field['label'] !== '' ? $field['label'] : __('field :n', ['n' => $fieldIndex + 1]);
                $attributes["types.$index.fields.$fieldIndex.key"] = __(':label key', ['label' => $label]);
                $attributes["types.$index.fields.$fieldIndex.label"] = __('field :n label', ['n' => $fieldIndex + 1]);
                $attributes["types.$index.fields.$fieldIndex.type"] = __(':label type', ['label' => $label]);
            }
        }

        return $attributes;
    }

    private function uniqueFieldKey(int $typeIndex): string
    {
        $existing = array_column($this->types[$typeIndex]['fields'], 'key');
        $counter = count($this->types[$typeIndex]['fields']) + 1;

        do {
            $key = "field_{$counter}";
            $counter++;
        } while (in_array($key, $existing, true));

        return $key;
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

    public function render(): View
    {
        return $this->view()
            ->title(__('Content Types'))
            ->layout('layouts::admin');
    }
};
?>

<x-admin.settings-layout>
    <div class="grid md:grid-cols-5 gap-10 items-start">
        <div class="space-y-4 md:col-span-3">
            <div>
                <flux:label>{{ __('Content types') }}</flux:label>
                <flux:text variant="subtle" class="mt-1">{{ __('Reusable types of content such as products, services or events, each with its own fields and pages.') }}</flux:text>
            </div>

            <form wire:submit="update" wire:warn-dirty="{{ __('Leaving? Changes you made may not be saved.') }}" class="space-y-10">
                <div class="space-y-4">
                    @if ($types === [])
                        <flux:text variant="subtle">{{ __('No content types yet. Add a preset or a custom type below.') }}</flux:text>
                    @else
                        <div wire:sort="reorderTypes" class="space-y-3">
                            @foreach ($types as $index => $type)
                                <x-admin.record-type-section :type="$type" :index="$index" />
                            @endforeach
                        </div>
                    @endif

                    <flux:dropdown>
                        <flux:button type="button" icon="plus" icon-trailing="chevron-down">{{ __('Add') }}</flux:button>

                        <flux:menu>
                            @foreach ($this->availablePresets as $preset)
                                <flux:menu.item :icon="$preset['icon']" wire:click="addPreset('{{ $preset['key'] }}')">
                                    {{ $preset['name'] }}
                                </flux:menu.item>
                            @endforeach

                            @if ($this->availablePresets !== [])
                                <flux:menu.separator />
                            @endif

                            <flux:menu.item icon="pencil-square" wire:click="addCustom">{{ __('Custom type') }}</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </div>
                <div>
                    <flux:button type="submit" variant="primary" icon="check">
                        {{ __('Update') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </div>

    <flux:modal name="confirm-delete-type" class="md:w-96">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Delete content type?') }}</flux:heading>
            <flux:text>{{ __('The type and its blueprint are removed. Existing records of this type are not affected by this screen yet.') }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button wire:click="removeType" variant="danger">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</x-admin.settings-layout>

@section('header-content')
    <flux:breadcrumbs class="hidden md:flex">
        <flux:breadcrumbs.item href="{{ route('admin.settings-general') }}" wire:navigate>{{ __('Settings') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ __('Content Types') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>
@endsection
