@props(['block'])

@php
    $content = $block->content ?? [];
    $heading = $block->text('heading');
    $rawItems = is_array($content['items'] ?? null) ? $content['items'] : [];
    $iconMode = ($content['icon'] ?? 'chevron') === 'plus-minus' ? 'plus-minus' : 'chevron';
    $exclusive = (bool) ($content['exclusive'] ?? true);
    $hasBg = (bool) ($content['hasBackground'] ?? false);

    $items = collect($rawItems)
        ->map(fn (mixed $item, int $i): array => [
            'title' => $block->text("items.{$i}.title"),
            'body' => $block->text("items.{$i}.body"),
        ])
        ->filter(fn (array $item): bool => $item['title'] !== '' || strip_tags($item['body']) !== '')
        ->values();
@endphp

<section
    class="w-full py-18"
    @if ($hasBg) style="background-color:var(--wire-card-bg);color:var(--wire-card-text)" @endif
>
    <div class="mx-auto max-w-7xl px-6">
        <div class="max-w-3xl">
            @if ($heading)
                <div class="mb-8 tracking-tight [&>p]:m-0 [&_a]:underline" style="font-size:var(--wire-heading-size, 1.5rem)">{!! $heading !!}</div>
            @endif

            @if ($items->isNotEmpty())
                <flux:accordion class="site-accordion" data-icon="{{ $iconMode }}" :exclusive="$exclusive" transition>
                    @foreach ($items as $item)
                        <flux:accordion.item :expanded="$loop->first">
                            <flux:accordion.heading>{{ $item['title'] }}</flux:accordion.heading>
                            <flux:accordion.content>
                                <div class="leading-relaxed [&_a]:underline [&>p]:my-2 [&_ul]:list-disc [&_ol]:list-decimal [&_ul]:pl-5 [&_ol]:pl-5 *:first:mt-0 *:last:mb-0">{!! $item['body'] !!}</div>
                            </flux:accordion.content>
                        </flux:accordion.item>
                    @endforeach
                </flux:accordion>
            @endif
        </div>
    </div>
</section>
