@props(['active' => []])

@php
    $locales = collect(resolve('localization')->getActiveLocales());
    $total = $locales->count();
@endphp

<div class="flex items-center gap-1 whitespace-nowrap cursor-default">
    @foreach ($locales->take(4) as $code => $meta)
        <flux:tooltip content="{{ $meta['name'] ?? $code }}">
            <flux:badge class="uppercase" size="sm" :color="in_array($code, $active, true) ? 'green' : 'zinc'">
                {{ $code }}
            </flux:badge>
        </flux:tooltip>
    @endforeach

    @if ($total > 5)
        <flux:dropdown position="bottom" align="end">
            <flux:button class="uppercase" size="xs" variant="subtle">+ {{ $total - 4 }} {{ __('more') }}</flux:button>
            <flux:menu class="max-w-2xl flex-wrap">
                @foreach ($locales->skip(4) as $code => $meta)
                    <flux:tooltip content="{{ $meta['name'] ?? $code }}">
                        <flux:badge class="uppercase my-0.5" size="sm" :color="in_array($code, $active, true) ? 'green' : 'zinc'">
                            {{ $code }}
                        </flux:badge>
                    </flux:tooltip>
                @endforeach
            </flux:menu>
        </flux:dropdown>
    @elseif ($total === 5)
        @foreach ($locales->skip(4) as $code => $meta)
            <flux:tooltip content="{{ $meta['name'] ?? $code }}">
                <flux:badge class="uppercase" size="sm" :color="in_array($code, $active, true) ? 'green' : 'zinc'">
                    {{ $code }}
                </flux:badge>
            </flux:tooltip>
        @endforeach
    @endif
</div>
