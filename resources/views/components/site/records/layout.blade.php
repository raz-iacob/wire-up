@props(['record'])

@php
    $type = $record->recordType;
    $mainFields = array_values(array_filter($type->fieldsPlacedIn(\App\Enums\FieldPlacement::MAIN), $record->hasFieldContent(...)));
    $sidebarFields = array_values(array_filter($type->fieldsPlacedIn(\App\Enums\FieldPlacement::SIDEBAR), $record->hasFieldContent(...)));
    $headingField = $type->fieldByKey('heading');
    $priceField = $type->fieldByKey(\App\Services\RecordTypePresets::PRICE_FIELD);
    $purchasable = resolve(\App\Services\ShopService::class)->isPurchasable($record);
    $priceShown = $priceField !== null && $type->placementFor($priceField) !== \App\Enums\FieldPlacement::HIDDEN;
    $looseBuyBox = $purchasable && ! $priceShown;
    $showDescription = $type->fieldByKey('overview') === null && mb_trim($record->description) !== '';

    if ($mainFields === [] && $headingField !== null && ! $showDescription) {
        $mainFields = $sidebarFields;
        $sidebarFields = [];
    }

    $hasSidebar = $sidebarFields !== [];
@endphp

<section class="w-full">
    <div class="mx-auto max-w-(--wire-container) px-(--wire-gutter) py-16">
        @if ($headingField !== null && $type->placementFor($headingField) === \App\Enums\FieldPlacement::HIDDEN)
            <h1 class="sr-only">{{ $record->displayHeading() }}</h1>
        @endif

        <div @class(['md:grid md:grid-cols-3 md:items-start md:gap-10' => $hasSidebar])>
            <div class="flex min-w-0 flex-col gap-5 md:col-span-2" data-placement="main">
                @if ($headingField === null)
                    <h1 class="text-(length:--wire-heading-size) tracking-tight">{{ $record->displayHeading() }}</h1>
                @endif

                @if ($showDescription)
                    <p class="leading-(--wire-body-leading)">{{ $record->description }}</p>
                @endif

                @foreach ($mainFields as $field)
                    <x-site.records.field
                        :record="$record"
                        :field="$field"
                        :purchasable="$purchasable"
                        :sidebar="false"
                    />
                @endforeach

                @if ($looseBuyBox && ! $hasSidebar)
                    <livewire:site.buy-box :record="$record" :key="'buy-box-'.$record->id" />
                @endif
            </div>

            @if ($hasSidebar)
                <aside class="mt-10 flex min-w-0 flex-col gap-5 md:mt-0" data-placement="sidebar">
                    @foreach ($sidebarFields as $field)
                        <x-site.records.field
                            :record="$record"
                            :field="$field"
                            :purchasable="$purchasable"
                            :sidebar="true"
                        />
                    @endforeach

                    @if ($looseBuyBox)
                        <livewire:site.buy-box :record="$record" :key="'buy-box-'.$record->id" />
                    @endif
                </aside>
            @endif
        </div>
    </div>
</section>
