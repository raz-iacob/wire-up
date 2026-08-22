@props(['items' => []])

@php
    $currentUrl = mb_rtrim(url()->current(), '/');

    $groups = \App\Services\SettingsService::groupMenuItems($items);
@endphp

@if ($groups !== [])
    <nav
        {{ $attributes->merge(['class' => 'flex flex-col gap-6']) }}
        x-data="{
            active: null,
            activeClass: 'border-(--wire-accent) font-medium',
            inactiveClass: 'border-transparent opacity-70 hover:opacity-100',
            init() {
                const targets = [...$el.querySelectorAll('a[data-spy]')]
                    .map((link) => document.getElementById(link.dataset.spy))
                    .filter(Boolean);

                if (! targets.length) {
                    return;
                }

                const observer = new IntersectionObserver(
                    (entries) => {
                        entries.forEach((entry) => {
                            if (entry.isIntersecting) {
                                this.active = entry.target.id;
                            }
                        });
                    },
                    { rootMargin: '0px 0px -65% 0px' },
                );

                targets.forEach((target) => observer.observe(target));
            },
        }"
    >
        @foreach ($groups as $group)
            <div wire:key="navgroup-{{ $loop->index }}">
                @if ($group['heading'] !== '')
                    <p class="mb-2 text-xs font-semibold tracking-wide uppercase opacity-60">{{ $group['heading'] }}</p>
                @endif

                @if ($group['items'] !== [])
                    <div class="flex flex-col gap-px border-s border-current/15">
                        @foreach ($group['items'] as $item)
                            @php
                                $fragment = \Illuminate\Support\Str::contains($item['url'], '#') ? \Illuminate\Support\Str::after($item['url'], '#') : '';
                                $serverActive = $fragment === '' && $item['url'] !== '' && mb_rtrim($item['url'], '/') === $currentUrl;
                            @endphp
                            @if (($item['type'] ?? 'link') === 'logout')
                                <x-site.logout-form
                                    :url="$item['url']"
                                    :label="$item['label']"
                                    button-class="-ms-px flex w-full items-center gap-2.5 border-s-2 border-transparent py-1.5 ps-4 text-start text-sm opacity-70 transition hover:opacity-100"
                                />

                                @continue
                            @endif

                            <a
                                href="{{ $item['url'] }}"
                                @if ($item['target'] === '_blank') target="_blank" rel="noopener noreferrer" @endif
                                @if ($fragment !== '')
                                    data-spy="{{ $fragment }}"
                                    x-bind:class="active === @js($fragment) ? activeClass : inactiveClass"
                                    x-bind:aria-current="active === @js($fragment) ? 'true' : null"
                                @elseif ($serverActive)
                                    aria-current="page"
                                @endif
                                @class([
                                    '-ms-px flex items-center gap-2.5 border-s-2 py-1.5 ps-4 text-sm transition',
                                    'border-(--wire-accent) font-medium' => $serverActive,
                                    'border-transparent opacity-70 hover:opacity-100' => ! $serverActive && $fragment === '',
                                ])
                            >
                                @if ($item['icon'] !== null)
                                    <flux:icon :name="$item['icon']" variant="micro" class="size-4 shrink-0" />
                                @endif

                                <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>

                                <x-site.nav-badge :badge="$item['badge']" :color="$item['badgeColor']" shape="chip" />
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </nav>
@endif
