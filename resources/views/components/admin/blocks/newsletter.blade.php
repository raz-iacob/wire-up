@props(['block', 'locale', 'multiLocale' => false, 'index', 'pageOptions' => []])

@php
    $c = "blocks.{$index}.content";
    $provider = resolve(\App\Services\NewsletterService::class)->providerName();
@endphp

<div class="flex flex-col gap-6">
    @if ($provider === null)
        <flux:callout icon="information-circle" variant="secondary">
            <flux:callout.text>
                {{ __('Sign-ups are kept in the Inbox. Connect Mailchimp, Kit or Buttondown in Settings → App Integrations to add them to your list as well.') }}</flux:callout.text>
        </flux:callout>
    @endif

    <x-forms.texteditor-translated
        name="{{ $c }}.heading"
        :locale="$locale"
        :multi-locale="$multiLocale"
        label="{{ __('Heading') }}"
    />
    <x-admin.blocks.partials.heading-level :name="$c" />
    <x-forms.texteditor-translated
        name="{{ $c }}.intro"
        :locale="$locale"
        :multi-locale="$multiLocale"
        label="{{ __('Intro') }}"
    />

    <flux:radio.group wire:model.live="{{ $c }}.layout" variant="segmented" label="{{ __('Layout') }}">
        <flux:radio value="inline" label="{{ __('One line') }}" />
        <flux:radio value="stacked" label="{{ __('Stacked') }}" />
    </flux:radio.group>

    <flux:switch wire:model.live="{{ $c }}.askName" label="{{ __('Ask for a first name') }}" align="left" />

    @if (data_get($block, 'content.askName') === true)
        <x-forms.input-translated
            name="{{ $c }}.namePlaceholder"
            :locale="$locale"
            :multi-locale="$multiLocale"
            label="{{ __('First name placeholder') }}"
        />
    @endif

    <x-forms.input-translated
        name="{{ $c }}.emailPlaceholder"
        :locale="$locale"
        :multi-locale="$multiLocale"
        label="{{ __('Email placeholder') }}"
    />
    <x-forms.input-translated
        name="{{ $c }}.buttonText"
        :locale="$locale"
        :multi-locale="$multiLocale"
        label="{{ __('Button text') }}"
    />
    <x-forms.texteditor-translated
        name="{{ $c }}.successMessage"
        :locale="$locale"
        :multi-locale="$multiLocale"
        label="{{ __('Success message') }}"
    />
    <x-forms.texteditor-translated
        name="{{ $c }}.privacyNote"
        :locale="$locale"
        :multi-locale="$multiLocale"
        label="{{ __('Small print') }}"
    />

    <div class="grid gap-4 md:grid-cols-2">
        <flux:radio.group wire:model.lazy="{{ $c }}.align" variant="segmented" label="{{ __('Alignment') }}">
            <flux:radio value="left" icon="bars-3-bottom-left" label="{{ __('Left') }}" />
            <flux:radio value="center" icon="bars-3" label="{{ __('Center') }}" />
        </flux:radio.group>

        <flux:switch wire:model.live="{{ $c }}.hasBackground" label="{{ __('Use background color') }}" align="left" />
    </div>
</div>
