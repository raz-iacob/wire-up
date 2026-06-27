@props(['items' => []])

@php
    $currentUrl = rtrim(url()->current(), '/');

    $groups = [];
    $current = ['heading' => '', 'items' => []];

    foreach ($items as $item) {
        if (($item['type'] ?? 'link') === 'heading') {
            if ($current['heading'] !== '' || $current['items'] !== []) {
                $groups[] = $current;
            }
            $current = ['heading' => $item['label'], 'items' => []];
        } else {
            $current['items'][] = $item;
        }
    }

    if ($current['heading'] !== '' || $current['items'] !== []) {
        $groups[] = $current;
    }
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

                const observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            this.active = entry.target.id;
                        }
                    });
                }, { rootMargin: '0px 0px -65% 0px' });

                targets.forEach((target) => observer.observe(target));
            },
        }"
    >
        @foreach ($groups as $group)
            <div wire:key="navgroup-{{ $loop->index }}">
                @if ($group['heading'] !== '')
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide opacity-60">{{ $group['heading'] }}</p>
                @endif

                @if ($group['items'] !== [])
                    <div class="flex flex-col gap-px border-s border-current/15">
                        @foreach ($group['items'] as $item)
                            @php
                                $fragment = \Illuminate\Support\Str::contains($item['url'], '#') ? \Illuminate\Support\Str::after($item['url'], '#') : '';
                                $serverActive = $fragment === '' && $item['url'] !== '' && rtrim($item['url'], '/') === $currentUrl;
                                $badgeClasses = match ($item['badgeColor']) {
                                    'primary' => 'bg-(--wire-primary-bg) text-(--wire-primary-text)',
                                    'green' => 'bg-green-100 text-green-700',
                                    'red' => 'bg-red-100 text-red-700',
                                    'amber' => 'bg-amber-100 text-amber-700',
                                    'blue' => 'bg-blue-100 text-blue-700',
                                    'purple' => 'bg-purple-100 text-purple-700',
                                    default => 'bg-current/10',
                                };
                            @endphp
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

                                @if ($item['badge'] !== '')
                                    <span class="shrink-0 rounded-(--wire-radius) px-1.5 py-0.5 text-xs font-medium {{ $badgeClasses }}">{{ $item['badge'] }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </nav>
@endif
