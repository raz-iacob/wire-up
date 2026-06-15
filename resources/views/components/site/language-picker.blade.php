@props(['languages' => null, 'align' => 'end'])

@if ($languages->count() > 1)
<flux:dropdown position="bottom" :align="$align">
    <button type="button"
        class="inline-flex items-center gap-1.5 text-sm font-medium text-(--wire-header-text) transition-opacity hover:opacity-70"
        aria-label="{{ __('Language') }}">
        <flux:icon name="globe-alt" variant="micro" />
        <span>{{ data_get($languages->firstWhere('current', true), 'label') }}</span>
        <flux:icon name="chevron-down" variant="micro" />
    </button>

    <flux:navmenu class="site-language-menu">
        @foreach ($languages as $language)
        <flux:navmenu.item href="{{ $language['url'] }}" :current="$language['current']">{{ $language['label'] }}
        </flux:navmenu.item>
        @endforeach
    </flux:navmenu>
</flux:dropdown>
@endif