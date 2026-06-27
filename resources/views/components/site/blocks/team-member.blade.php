@props(['member', 'layout' => 'circle', 'hasBg' => false])

@php
    $bio = strip_tags($member['bio']) !== '' ? $member['bio'] : '';
    $bioClass = 'leading-relaxed opacity-80 [&_a]:text-(--wire-accent) [&_a]:underline [&>p]:my-2 *:first:mt-0 *:last:mb-0';
    $cardClass = $hasBg ? 'bg-(--wire-body-bg) text-(--wire-body-text)' : 'bg-(--wire-card-bg) text-(--wire-card-text)';
@endphp

@switch($layout)
    @case('card')
        <article class="wire-card flex h-full flex-col overflow-hidden rounded-(--wire-radius) shadow-sm {{ $cardClass }}">
            @if ($member['photo'])
                <img src="{{ $member['photo'] }}" alt="{{ $member['alt'] }}" loading="lazy" class="aspect-square w-full object-cover" />
            @endif
            <div class="flex grow flex-col gap-3 p-6">
                @if ($member['role'] !== '')
                    <div class="text-xs font-semibold uppercase tracking-wide opacity-60">{{ $member['role'] }}</div>
                @endif
                @if ($member['name'] !== '')
                    <h3 class="text-lg font-semibold tracking-tight">{{ $member['name'] }}</h3>
                @endif
                @if ($bio !== '')
                    <div class="{{ $bioClass }}">{!! $bio !!}</div>
                @endif
                <x-site.blocks.partials.team-socials :socials="$member['socials']" justify="start" class="mt-1" />
            </div>
        </article>
        @break

    @case('overlay')
        <article
            tabindex="0"
            class="group relative flex aspect-[3/4] flex-col justify-end overflow-hidden rounded-(--wire-radius) bg-(--wire-primary-bg) shadow-sm focus:outline-none"
        >
            @if ($member['photo'])
                <img src="{{ $member['photo'] }}" alt="{{ $member['alt'] }}" loading="lazy" class="absolute inset-0 size-full object-cover" />
            @endif

            <div class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/15 to-transparent transition-opacity duration-300 group-hover:opacity-0 group-focus-within:opacity-0"></div>

            <div class="absolute inset-0 bg-(--wire-primary-bg) opacity-0 transition-opacity duration-300 group-hover:opacity-100 group-focus-within:opacity-100"></div>

            <div class="relative p-6 text-white transition-colors duration-300 group-hover:text-(--wire-primary-text) group-focus-within:text-(--wire-primary-text)">
                @if ($member['name'] !== '')
                    <h3 class="text-xl font-semibold tracking-tight">{{ $member['name'] }}</h3>
                @endif
                @if ($member['role'] !== '')
                    <div class="mt-1 text-sm opacity-80">{{ $member['role'] }}</div>
                @endif

                <div class="grid grid-rows-[0fr] opacity-0 transition-all duration-300 group-hover:grid-rows-[1fr] group-hover:opacity-100 group-focus-within:grid-rows-[1fr] group-focus-within:opacity-100">
                    <div class="overflow-hidden">
                        @if ($bio !== '')
                            <div class="mt-3 {{ $bioClass }}">{!! $bio !!}</div>
                        @endif
                        <x-site.blocks.partials.team-socials :socials="$member['socials']" justify="start" class="mt-4" />
                    </div>
                </div>
            </div>
        </article>
        @break

    @case('portrait')
        <article class="flex h-full flex-col">
            @if ($member['photo'])
                <img src="{{ $member['photo'] }}" alt="{{ $member['alt'] }}" loading="lazy" class="aspect-square w-full rounded-(--wire-radius) object-cover shadow-sm" />
            @endif
            @if ($member['name'] !== '')
                <h3 class="mt-4 text-lg font-semibold tracking-tight">{{ $member['name'] }}</h3>
            @endif
            @if ($member['role'] !== '')
                <div class="mt-0.5 text-sm opacity-70">{{ $member['role'] }}</div>
            @endif
            @if ($bio !== '')
                <div class="mt-3 {{ $bioClass }}">{!! $bio !!}</div>
            @endif
            <x-site.blocks.partials.team-socials :socials="$member['socials']" justify="start" class="mt-4" />
        </article>
        @break

    @default
        <article class="wire-card flex h-full flex-col items-center rounded-(--wire-radius) p-8 text-center shadow-sm {{ $cardClass }}">
            @if ($member['photo'])
                <img src="{{ $member['photo'] }}" alt="{{ $member['alt'] }}" loading="lazy" class="size-28 rounded-full object-cover" />
            @endif
            @if ($member['name'] !== '')
                <h3 class="mt-4 text-lg font-semibold tracking-tight">{{ $member['name'] }}</h3>
            @endif
            @if ($member['role'] !== '')
                <div class="mt-0.5 text-sm opacity-70">{{ $member['role'] }}</div>
            @endif
            @if ($bio !== '')
                <div class="mt-3 {{ $bioClass }}">{!! $bio !!}</div>
            @endif
            <x-site.blocks.partials.team-socials :socials="$member['socials']" justify="center" class="mt-4" />
        </article>
@endswitch
