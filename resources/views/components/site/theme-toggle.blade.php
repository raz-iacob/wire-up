@php($site = \App\Services\SettingsService::current())

@if ($site->themeToggleEnabled())
    <button
        type="button"
        onclick="window.wireupToggleScheme && window.wireupToggleScheme()"
        aria-label="{{ __('Toggle light and dark theme') }}"
        title="{{ __('Toggle theme') }}"
        {{ $attributes->merge(['class' => 'inline-flex items-center justify-center rounded-(--wire-radius) p-1.5 text-current transition hover:opacity-70 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-current']) }}
    >
        <flux:icon.moon variant="mini" class="size-5 dark:hidden" />
        <flux:icon.sun variant="mini" class="hidden size-5 dark:block" />
    </button>
@endif
