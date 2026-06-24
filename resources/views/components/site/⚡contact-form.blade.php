<?php

declare(strict_types=1);

use App\Actions\CreateSubmissionAction;
use App\Models\Block;
use App\Services\SettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

return new class extends Component
{
    private const int MIN_SECONDS = 3;

    private const int MAX_ATTEMPTS = 5;

    private const int DECAY_SECONDS = 60;

    /**
     * @var array<string, mixed>
     */
    #[Locked]
    public array $config = [];

    #[Locked]
    public ?int $blockId = null;

    #[Locked]
    public ?int $pageId = null;

    #[Locked]
    public int $startedAt = 0;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $subject = '';

    public string $message = '';

    /**
     * @var array<string, mixed>
     */
    public array $custom = [];

    public string $website = '';

    public bool $sent = false;

    public function mount(): void
    {
        $this->startedAt = now()->timestamp;
        $this->initCustomDefaults();
    }

    public function submit(CreateSubmissionAction $action): void
    {
        if ($this->website !== '' || now()->timestamp - $this->startedAt < self::MIN_SECONDS) {
            $this->addError('form', __('Sorry, your message could not be sent. Please try again.'));

            return;
        }

        $throttleKey = 'contact-form:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $this->addError('form', __('Too many attempts. Please wait a moment and try again.'));

            return;
        }

        RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

        $this->validate($this->validationRules(), [], $this->validationAttributes());

        $action->handle([
            'page_id' => $this->pageId,
            'block_id' => $this->blockId,
            'type' => 'contact',
            'form_name' => $this->formName(),
            'name' => $this->valueFor('name'),
            'email' => $this->valueFor('email'),
            'phone' => $this->valueFor('phone'),
            'subject' => $this->valueFor('subject'),
            'message' => $this->valueFor('message'),
            'metadata' => $this->customMetadata(),
            'ip' => request()->ip(),
            'locale' => app()->getLocale(),
            'country' => request()->header('CF-IPCOUNTRY'),
        ], $this->recipients());

        $this->reset(['name', 'email', 'phone', 'subject', 'message', 'custom', 'website']);
        $this->initCustomDefaults();
        $this->sent = true;
    }

    /**
     * @return array<int, array{key: string, label: string, aria: string, placeholder: string, type: string, required: bool, column: string}>
     */
    #[Computed]
    public function formFields(): array
    {
        $defaults = ['name' => __('Name'), 'email' => __('Email'), 'phone' => __('Phone'), 'subject' => __('Subject'), 'message' => __('Message')];
        $types = ['name' => 'text', 'email' => 'email', 'phone' => 'tel', 'subject' => 'text', 'message' => 'textarea'];

        $order = data_get($this->config, 'fieldOrder', array_keys($defaults));
        $order = is_array($order) ? $order : array_keys($defaults);

        $fields = [];

        foreach ($order as $key) {
            if (! is_string($key)) {
                continue;
            }
            if (! isset($defaults[$key])) {
                continue;
            }
            $label = $this->localizedField("fields.{$key}.label");
            $placeholder = $this->localizedField("fields.{$key}.placeholder");

            $fields[] = [
                'key' => $key,
                'label' => $label !== '' ? $label : ($placeholder !== '' ? '' : $defaults[$key]),
                'aria' => $label !== '' ? $label : ($placeholder !== '' ? $placeholder : $defaults[$key]),
                'placeholder' => $placeholder,
                'type' => $types[$key],
                'required' => (bool) data_get($this->config, "fields.{$key}.required", false),
                'column' => $this->columnFor("fields.{$key}.column"),
            ];
        }

        return $fields;
    }

    /**
     * @return array<int, array{id: string, label: string, aria: string, type: string, required: bool, options: array<int, string>, column: string}>
     */
    #[Computed]
    public function customFields(): array
    {
        $fields = data_get($this->config, 'customFields', []);

        if (! is_array($fields)) {
            return [];
        }

        $resolved = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }
            if (! is_string($field['id'] ?? null)) {
                continue;
            }
            $label = $this->localized(is_array($field['label'] ?? null) ? $field['label'] : []);
            $resolved[] = [
                'id' => $field['id'],
                'label' => $label,
                'aria' => $label !== '' ? $label : __('Field'),
                'type' => is_string($field['type'] ?? null) ? $field['type'] : 'text',
                'required' => (bool) ($field['required'] ?? false),
                'options' => $this->optionLines(is_string($field['options'] ?? null) ? $field['options'] : ''),
                'column' => $this->columnFor("customFields.{$field['id']}.column"),
            ];
        }

        return $resolved;
    }

    /**
     * @return array<int, array{kind: string, field: array<string, mixed>}>
     */
    #[Computed]
    public function orderedFields(): array
    {
        $builtins = [];

        foreach ($this->formFields() as $field) {
            $builtins[$field['key']] = $field;
        }

        $customs = [];

        foreach ($this->customFields() as $field) {
            $customs[$field['id']] = $field;
        }

        $order = data_get($this->config, 'fieldOrder', []);
        $order = is_array($order) ? $order : [];

        $items = [];

        foreach ($order as $token) {
            if (! is_string($token)) {
                continue;
            }

            if (isset($builtins[$token])) {
                $items[] = ['kind' => 'builtin', 'field' => $builtins[$token]];
                unset($builtins[$token]);
            } elseif (isset($customs[$token])) {
                $items[] = ['kind' => 'custom', 'field' => $customs[$token]];
                unset($customs[$token]);
            }
        }

        foreach ($builtins as $field) {
            $items[] = ['kind' => 'builtin', 'field' => $field];
        }

        foreach ($customs as $field) {
            $items[] = ['kind' => 'custom', 'field' => $field];
        }

        return $items;
    }

    #[Computed]
    public function heading(): string
    {
        return $this->localizedField('heading');
    }

    #[Computed]
    public function intro(): string
    {
        return $this->localizedField('description');
    }

    #[Computed]
    public function submitLabel(): string
    {
        $label = $this->localizedField('submitText');

        return $label !== '' ? $label : __('Send');
    }

    #[Computed]
    public function successMessage(): string
    {
        $message = $this->localizedField('successMessage');

        return $message !== '' ? $message : __('Thanks, your message has been sent.');
    }

    #[Computed]
    public function layout(): string
    {
        $layout = data_get($this->config, 'layout', 'stacked');

        return in_array($layout, ['stacked', 'split', 'full'], true) ? $layout : 'stacked';
    }

    public function render(): View
    {
        return $this->view();
    }

    private function initCustomDefaults(): void
    {
        foreach ($this->customFields() as $field) {
            $this->custom[$field['id']] = $field['type'] === 'checkbox' ? false : '';
        }
    }

    private function builtinPresent(string $key): bool
    {
        $order = data_get($this->config, 'fieldOrder', []);

        return is_array($order) && in_array($key, $order, true);
    }

    private function columnFor(string $path): string
    {
        return data_get($this->config, $path) === 'right' ? 'right' : 'left';
    }

    private function formName(): ?string
    {
        $name = data_get($this->config, 'formName', '');

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function validationRules(): array
    {
        $rules = [];

        foreach ($this->formFields() as $field) {
            $rule = [$field['required'] ? 'required' : 'nullable'];

            if ($field['key'] === 'email') {
                $rule[] = 'email';
                $rule[] = 'max:255';
            } elseif ($field['key'] === 'message') {
                $rule[] = 'string';
                $rule[] = 'max:5000';
            } else {
                $rule[] = 'string';
                $rule[] = 'max:255';
            }

            $rules[$field['key']] = $rule;
        }

        foreach ($this->customFields() as $field) {
            $rules['custom.'.$field['id']] = $this->customRule($field);
        }

        return $rules;
    }

    /**
     * @param  array{id: string, label: string, aria: string, type: string, required: bool, options: array<int, string>, column: string}  $field
     * @return array<int, mixed>
     */
    private function customRule(array $field): array
    {
        if ($field['type'] === 'checkbox') {
            return [$field['required'] ? 'accepted' : 'boolean'];
        }

        $rule = [$field['required'] ? 'required' : 'nullable'];

        $rule[] = match ($field['type']) {
            'email' => 'email',
            'number' => 'numeric',
            'select' => Rule::in($field['options']),
            default => 'string',
        };

        if ($field['type'] === 'textarea') {
            $rule[] = 'max:5000';
        } elseif (in_array($field['type'], ['text', 'tel'], true)) {
            $rule[] = 'max:255';
        }

        return $rule;
    }

    /**
     * @return array<string, string>
     */
    private function validationAttributes(): array
    {
        $attributes = [];

        foreach ($this->formFields() as $field) {
            $attributes[$field['key']] = $field['aria'];
        }

        foreach ($this->customFields() as $field) {
            $attributes['custom.'.$field['id']] = $field['aria'];
        }

        return $attributes;
    }

    private function valueFor(string $key): ?string
    {
        if (! $this->builtinPresent($key)) {
            return null;
        }

        $value = match ($key) {
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'subject' => $this->subject,
            'message' => $this->message,
            default => '',
        };

        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, array{label: string, value: mixed}>
     */
    private function customMetadata(): array
    {
        $data = [];

        foreach ($this->customFields() as $field) {
            $data[$field['id']] = [
                'label' => $field['label'],
                'value' => $this->custom[$field['id']] ?? null,
            ];
        }

        return $data;
    }

    /**
     * @return array<int, string>
     */
    private function recipients(): array
    {
        $raw = '';

        if ($this->blockId !== null) {
            $block = Block::query()->find($this->blockId);
            $raw = (string) data_get($block?->content, 'recipient', '');
        }

        $emails = collect(preg_split('/[\s,]+/', $raw) ?: [])
            ->map(fn (string $email): string => mb_trim($email))
            ->filter(fn (string $email): bool => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->values()
            ->all();

        if ($emails !== []) {
            return $emails;
        }

        $setting = SettingsService::current()->contactEmail();

        if ($setting !== '') {
            return [$setting];
        }

        $from = config('mail.from.address');

        return is_string($from) && $from !== '' ? [$from] : [];
    }

    /**
     * @param  array<string, mixed>  $map
     */
    private function localized(array $map): string
    {
        $locale = app()->getLocale();
        $value = $map[$locale] ?? $map[config()->string('app.default_locale', 'en')] ?? '';

        return is_string($value) ? $value : '';
    }

    private function localizedField(string $key): string
    {
        $map = data_get($this->config, $key);

        return $this->localized(is_array($map) ? $map : []);
    }

    /**
     * @return array<int, string>
     */
    private function optionLines(string $options): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $options) ?: [])
            ->map(fn (string $line): string => mb_trim($line))
            ->filter(fn (string $line): bool => $line !== '')
            ->values()
            ->all();
    }
};
?>

@php
    $inputClass = 'w-full border px-4 py-3 text-base focus:outline-none rounded-(--wire-radius) bg-(--wire-input-bg) text-(--wire-input-text) border-(--wire-input-border)';
@endphp

<div>
    @if ($this->heading !== '')
        <div class="mb-8 tracking-tight [&>p]:m-0 [&_a]:underline text-(length:--wire-heading-size)">{!! $this->heading !!}</div>
    @endif

    @if (strip_tags($this->intro) !== '')
        <div class="mb-6 leading-relaxed [&>p]:m-0 [&_a]:underline">{!! $this->intro !!}</div>
    @endif

    @if ($this->sent)
        <div class="border p-4 leading-relaxed [&>p]:m-0 [&_a]:underline rounded-(--wire-radius) bg-(--wire-input-bg) text-(--wire-input-text) border-(--wire-input-border)">{!! $this->successMessage !!}</div>
    @else
        <form wire:submit="submit" novalidate @class(['flex flex-col gap-5', 'mx-auto max-w-xl' => $this->layout === 'stacked'])>
            @if ($this->layout === 'split')
                <div class="md:grid md:grid-cols-2 md:items-start md:gap-8">
                    <div class="flex flex-col gap-5">
                        @foreach ($this->orderedFields as $item)
                            @if ($item['field']['column'] !== 'right')
                                @include('components.site.blocks.partials.contact-field', ['item' => $item, 'inputClass' => $inputClass])
                            @endif
                        @endforeach
                    </div>
                    <div class="flex flex-col gap-5 max-md:mt-5">
                        @foreach ($this->orderedFields as $item)
                            @if ($item['field']['column'] === 'right')
                                @include('components.site.blocks.partials.contact-field', ['item' => $item, 'inputClass' => $inputClass])
                            @endif
                        @endforeach
                    </div>
                </div>
            @else
                <div @class(['grid gap-5', 'md:grid-cols-2' => $this->layout === 'full'])>
                    @foreach ($this->orderedFields as $item)
                        @include('components.site.blocks.partials.contact-field', ['item' => $item, 'inputClass' => $inputClass, 'layout' => $this->layout])
                    @endforeach
                </div>
            @endif

            <div class="hidden" aria-hidden="true">
                <label>{{ __('Leave this field empty') }}
                    <input type="text" wire:model="website" tabindex="-1" autocomplete="off" data-1p-ignore data-lpignore="true" data-form-type="other" />
                </label>
            </div>

            @error('form')<div class="px-3 py-2 text-sm font-medium rounded-(--wire-radius) bg-red-100 text-red-700">{{ $message }}</div>@enderror

            <div>
                <button
                    type="submit"
                    class="inline-flex items-center justify-center px-6 py-3 text-base font-medium transition hover:opacity-90 disabled:opacity-50 rounded-(--wire-radius) bg-(--wire-primary-bg) text-(--wire-primary-text)"
                    wire:loading.attr="disabled"
                    wire:target="submit"
                >
                    <span wire:loading.remove wire:target="submit">{{ $this->submitLabel }}</span>
                    <span wire:loading wire:target="submit">{{ __('Sending…') }}</span>
                </button>
            </div>
        </form>
    @endif
</div>
