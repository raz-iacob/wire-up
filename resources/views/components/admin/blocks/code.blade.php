@props(['block', 'locale', 'multiLocale' => false, 'index'])

@php
    $c = "blocks.{$index}.content";
    $languages = [
        'plaintext' => __('Plain text'),
        'bash' => 'Shell / Bash',
        'php' => 'PHP',
        'javascript' => 'JavaScript',
        'typescript' => 'TypeScript',
        'html' => 'HTML',
        'css' => 'CSS',
        'json' => 'JSON',
        'yaml' => 'YAML',
        'sql' => 'SQL',
        'markdown' => 'Markdown',
    ];
@endphp

<div class="flex flex-col gap-6">
    <x-forms.texteditor-translated name="{{ $c }}.heading" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Heading') }}" />
    <x-forms.texteditor-translated name="{{ $c }}.intro" :locale="$locale" :multi-locale="$multiLocale" label="{{ __('Subheading') }}" />

    <flux:textarea wire:model.blur="{{ $c }}.code" label="{{ __('Code') }}" rows="12" class="font-mono text-sm" />

    <div class="grid gap-4 md:grid-cols-2">
        <flux:select variant="listbox" searchable wire:model.lazy="{{ $c }}.language" label="{{ __('Language') }}">
            @foreach ($languages as $value => $label)
                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model.blur="{{ $c }}.filename" label="{{ __('Filename') }}" placeholder="{{ __('Optional, e.g. app/Models/Page.php') }}" />
    </div>

    <flux:switch wire:model.live="{{ $c }}.wrap" label="{{ __('Wrap long lines') }}" align="left" />
    <flux:switch wire:model.live="{{ $c }}.hasBackground" label="{{ __('Use background color') }}" align="left" />
</div>
