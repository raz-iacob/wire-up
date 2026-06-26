@props(['block'])

@php
    $content = $block->content ?? [];
    $heading = $block->text('heading');
    $intro = $block->text('intro');
    $hasBg = (bool) ($content['hasBackground'] ?? false);
    $columns = (int) ($content['columns'] ?? 3);
    $columns = in_array($columns, [2, 3, 4], true) ? $columns : 3;
    $rawItems = is_array($content['items'] ?? null) ? $content['items'] : [];

    $socialPlatforms = config('social.platforms');
    $socialVariant = config('social.default_icon_variant', 'solid');

    $members = collect($rawItems)
        ->map(function (mixed $item, int $i) use ($block, $socialPlatforms, $socialVariant): array {
            $socials = [];

            foreach ((is_array(data_get($item, 'socials')) ? data_get($item, 'socials') : []) as $key => $url) {
                $url = mb_trim((string) $url);

                if ($url === '') {
                    continue;
                }

                if ($key === 'email') {
                    $socials[] = ['kind' => 'icon', 'name' => 'envelope', 'href' => 'mailto:'.$url, 'label' => __('Email')];
                } elseif ($key === 'website') {
                    $socials[] = ['kind' => 'icon', 'name' => 'globe-alt', 'href' => $url, 'label' => __('Website')];
                } elseif (isset($socialPlatforms[$key])) {
                    $icon = (string) data_get($socialPlatforms, "{$key}.icon", $key);
                    $socials[] = ['kind' => 'mask', 'src' => Vite::asset("resources/images/socials/{$icon}-{$socialVariant}.svg"), 'href' => $url, 'label' => ucfirst((string) $key)];
                }
            }

            return [
                'photo' => $block->imageUrl("items.{$i}.photo", ['w' => 400, 'h' => 400]),
                'alt' => $block->imageAlt("items.{$i}.photo") ?: $block->text("items.{$i}.name"),
                'name' => $block->text("items.{$i}.name"),
                'role' => $block->text("items.{$i}.role"),
                'bio' => $block->text("items.{$i}.bio"),
                'socials' => $socials,
            ];
        })
        ->filter(fn (array $member): bool => $member['name'] !== '' || $member['photo'] !== null)
        ->values();

    $hasHeading = strip_tags($heading) !== '' || strip_tags($intro) !== '';

    $gridCols = match ($columns) {
        2 => 'sm:grid-cols-2',
        4 => 'sm:grid-cols-2 lg:grid-cols-4',
        default => 'sm:grid-cols-2 lg:grid-cols-3',
    };
@endphp

@if ($members->isNotEmpty())
    <section @class([
        'w-full',
        'bg-(--wire-card-bg) text-(--wire-card-text)' => $hasBg,
        ($pad ?? 'py-16') => $hasBg,
    ])>
        <div class="mx-auto max-w-(--wire-container) px-(--wire-gutter)">
            @if ($hasHeading)
                <div class="mb-12">
                    @if (strip_tags($heading) !== '')
                        <div class="tracking-tight [&>p]:m-0 [&_a]:underline text-(length:--wire-heading-size)">{!! $heading !!}</div>
                    @endif
                    @if (strip_tags($intro) !== '')
                        <div class="mt-3 leading-relaxed opacity-80 [&_a]:underline [&>p]:my-2 *:first:mt-0 *:last:mb-0">{!! $intro !!}</div>
                    @endif
                </div>
            @endif

            <div class="grid grid-cols-1 gap-8 {{ $gridCols }}">
                @foreach ($members as $member)
                    <article class="flex flex-col items-center text-center" wire:key="team-member-{{ $loop->index }}">
                        @if ($member['photo'])
                            <img src="{{ $member['photo'] }}" alt="{{ $member['alt'] }}" loading="lazy" class="size-32 rounded-full object-cover shadow-sm" />
                        @endif

                        @if ($member['name'] !== '')
                            <h3 class="mt-4 text-lg font-semibold tracking-tight">{{ $member['name'] }}</h3>
                        @endif

                        @if ($member['role'] !== '')
                            <div class="mt-0.5 text-sm opacity-70">{{ $member['role'] }}</div>
                        @endif

                        @if (strip_tags($member['bio']) !== '')
                            <div class="mt-3 leading-relaxed opacity-80 [&_a]:underline [&>p]:my-2 *:first:mt-0 *:last:mb-0">{!! $member['bio'] !!}</div>
                        @endif

                        @if ($member['socials'] !== [])
                            <div class="mt-4 flex items-center justify-center gap-4">
                                @foreach ($member['socials'] as $social)
                                    <a
                                        href="{{ $social['href'] }}"
                                        @if ($social['kind'] === 'mask' || $social['name'] === 'globe-alt') target="_blank" rel="noopener noreferrer" @endif
                                        class="opacity-70 transition-opacity hover:opacity-100"
                                        aria-label="{{ $social['label'] }}"
                                    >
                                        @if ($social['kind'] === 'mask')
                                            <span
                                                class="block size-5 bg-current mask-center mask-no-repeat mask-contain"
                                                style="mask-image:url('{{ $social['src'] }}'); -webkit-mask-image:url('{{ $social['src'] }}');"
                                            ></span>
                                        @else
                                            <flux:icon name="{{ $social['name'] }}" class="size-5" />
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endif
