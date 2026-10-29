@props(['record', 'purchasable' => false])

@php
    $settings = \App\Services\SettingsService::current();
    $isSold = (bool) $record->fieldValue('sold', false) || $record->stock === 0;
    $price = $record->fieldValue(\App\Services\RecordTypePresets::PRICE_FIELD, false);
    $compare = $record->fieldValue('regular_price', false);
    $hasDiscount = is_numeric($price) && is_numeric($compare) && (float) $compare > (float) $price && (float) $price > 0;
    $discountPct = $hasDiscount ? (int) round((1 - (float) $price / (float) $compare) * 100) : null;
@endphp

@if (is_numeric($price) || $isSold || $purchasable)
    <div class="flex flex-col gap-5" data-field="{{ \App\Services\RecordTypePresets::PRICE_FIELD }}">
        @if (is_numeric($price) || $isSold)
            <div class="flex flex-col gap-1">
                <div class="flex flex-wrap items-center gap-3">
                    @if (is_numeric($price))
                        <span class="font-(family-name:--wire-heading-font) text-(length:--wire-heading-size) tracking-tight">{{ $settings->formatMoney($price) }}</span>
                    @endif
                    @if ($hasDiscount)
                        <span class="rounded-full bg-current/10 px-2.5 py-1 text-sm font-bold text-(--wire-accent)">{{ $discountPct }}%</span>
                    @endif
                    @if ($isSold)
                        <span class="rounded-full bg-current/10 px-2.5 py-1 text-sm font-bold text-(--wire-accent)">{{ __('Sold') }}</span>
                    @endif
                </div>
                @if ($hasDiscount)
                    <span class="line-through opacity-50">{{ $settings->formatMoney($compare) }}</span>
                @endif
            </div>
        @endif

        @if ($purchasable)
            <livewire:site.buy-box :record="$record" :key="'buy-box-'.$record->id" />
        @endif
    </div>
@endif
