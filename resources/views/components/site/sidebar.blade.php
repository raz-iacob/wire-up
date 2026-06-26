@props(['menu'])

@php
    $display = $menu['display'];
    $background = $display['background'];
    $mobile = $display['mobile'];
    $isRight = $display['position'] === 'right';

    $links = collect($menu['items'])->filter(fn (array $item): bool => $item['type'] !== 'heading')->values();
    $currentUrl = rtrim(url()->current(), '/');

    // Current location for the toggle button: "Group › Item".
    $activeItem = '';
    $activeGroup = '';
    $group = '';
    foreach ($menu['items'] as $candidate) {
        if ($candidate['type'] === 'heading') {
            $group = $candidate['label'];

            continue;
        }

        if ($candidate['url'] !== '' && rtrim($candidate['url'], '/') === $currentUrl) {
            $activeItem = $candidate['label'];
            $activeGroup = $group;

            break;
        }
    }

    $fallbackLabel = data_get(collect($menu['items'])->firstWhere('type', 'heading'), 'label') ?: __('Menu');
@endphp

<aside data-site-sidebar x-data="{ open: false }" class="w-full">
    {{-- Desktop: the full vertical panel --}}
    <div @class([
        'hidden md:block',
        'md:sticky md:top-24 md:self-start' => $display['sticky'],
    ])>
        <div @class(['rounded-(--wire-radius) bg-(--wire-card-bg) p-6 text-(--wire-card-text)' => $background])>
            <x-site.navlist :items="$menu['items']" />
        </div>
    </div>

    {{-- Mobile --}}
    @if ($mobile === 'collapse')
        <nav @class(['flex flex-wrap items-center gap-x-6 gap-y-2 md:hidden', 'justify-end' => $isRight])>
            @foreach ($links as $item)
                @php($active = $item['url'] !== '' && rtrim($item['url'], '/') === $currentUrl)
                <a
                    href="{{ $item['url'] }}"
                    @if ($item['target'] === '_blank') target="_blank" rel="noopener noreferrer" @endif
                    @if ($active) aria-current="page" @endif
                    @class([
                        'text-sm transition',
                        'font-medium underline underline-offset-8' => $active,
                        'opacity-70 hover:opacity-100' => ! $active,
                    ])
                >{{ $item['label'] }}</a>
            @endforeach
        </nav>
    @elseif ($mobile === 'toggle')
        <button
            type="button"
            x-on:click="open = true"
            x-bind:aria-expanded="open.toString()"
            @class(['flex items-center gap-2 text-sm md:hidden', 'ms-auto' => $isRight])
        >
            <flux:icon name="bars-3" class="size-5 shrink-0" />
            @if ($activeItem !== '')
                @if ($activeGroup !== '')
                    <span class="opacity-70">{{ $activeGroup }}</span>
                    <flux:icon name="chevron-right" variant="micro" class="size-3 shrink-0 opacity-50" />
                @endif
                <span class="font-medium">{{ $activeItem }}</span>
            @else
                <span class="font-medium">{{ $fallbackLabel }}</span>
            @endif
        </button>

        <div class="md:hidden" x-on:keydown.escape.window="open = false">
            <div
                x-show="open"
                x-cloak
                x-transition.opacity
                x-on:click="open = false"
                class="fixed inset-0 z-40 bg-black/40"
            ></div>

            <div
                x-show="open"
                x-cloak
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="{{ $isRight ? 'translate-x-full' : '-translate-x-full' }}"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="{{ $isRight ? 'translate-x-full' : '-translate-x-full' }}"
                @class([
                    'fixed inset-y-0 z-40 flex w-72 max-w-[80vw] flex-col gap-6 overflow-y-auto bg-(--wire-card-bg) px-6 py-6 text-(--wire-card-text) shadow-xl',
                    'right-0' => $isRight,
                    'left-0' => ! $isRight,
                ])
            >
                <div @class(['flex', 'justify-start' => $isRight, 'justify-end' => ! $isRight])>
                    <button type="button" x-on:click="open = false" aria-label="{{ __('Close menu') }}">
                        <flux:icon name="x-mark" class="size-6" />
                    </button>
                </div>

                <x-site.navlist :items="$menu['items']" />
            </div>
        </div>
    @endif
</aside>
