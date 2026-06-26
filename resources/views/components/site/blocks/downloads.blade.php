@props(['block'])

@php
    use Illuminate\Support\Number;

    $content = $block->content ?? [];
    $heading = $block->text('heading');
    $intro = $block->text('intro');
    $hasBg = (bool) ($content['hasBackground'] ?? false);
    $columns = (int) ($content['columns'] ?? 1);
    $columns = in_array($columns, [1, 2, 3, 4, 5], true) ? $columns : 1;
    $rawFiles = is_array($content['files'] ?? null) ? $content['files'] : [];

    $files = collect($rawFiles)
        ->map(fn (mixed $file, int $i): array => [
            'url' => $block->fileUrl("files.{$i}"),
            'name' => (string) (data_get($file, 'metadata.caption') ?: data_get($file, 'filename', __('Download'))),
            'size' => data_get($file, 'size'),
            'icon' => (string) data_get($file, 'icon', 'document'),
        ])
        ->filter(fn (array $file): bool => $file['url'] !== null)
        ->values();

    $hasHeading = strip_tags($heading) !== '' || strip_tags($intro) !== '';

    $gridCols = match ($columns) {
        2 => 'sm:grid-cols-2',
        3 => 'sm:grid-cols-2 lg:grid-cols-3',
        4 => 'sm:grid-cols-2 lg:grid-cols-4',
        5 => 'sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5',
        default => '',
    };
@endphp

@if ($files->isNotEmpty())
    <section @class([
        'w-full',
        'bg-(--wire-card-bg) text-(--wire-card-text)' => $hasBg,
        ($pad ?? 'py-16') => $hasBg,
    ])>
        <div class="mx-auto max-w-(--wire-container) px-(--wire-gutter)">
            @if ($hasHeading)
                <div class="mb-8">
                    @if (strip_tags($heading) !== '')
                        <div class="tracking-tight [&>p]:m-0 [&_a]:underline text-(length:--wire-heading-size)">{!! $heading !!}</div>
                    @endif
                    @if (strip_tags($intro) !== '')
                        <div class="mt-3 leading-relaxed opacity-80 [&_a]:underline [&>p]:my-2 *:first:mt-0 *:last:mb-0">{!! $intro !!}</div>
                    @endif
                </div>
            @endif

            <ul class="grid grid-cols-1 {{ $gridCols }} gap-3">
                @foreach ($files as $file)
                    <li wire:key="download-{{ $loop->index }}">
                        <a
                            href="{{ $file['url'] }}"
                            download
                            class="group flex items-center gap-4 rounded-(--wire-radius) border border-current/15 p-4 transition hover:bg-current/5"
                        >
                            <flux:icon name="{{ $file['icon'] }}" class="size-8 shrink-0 opacity-60" />
                            <span class="min-w-0 grow">
                                <span class="block truncate font-medium">{{ $file['name'] }}</span>
                                @if ($file['size'])
                                    <span class="block text-sm opacity-70">{{ Number::fileSize((int) $file['size'], precision: 1) }}</span>
                                @endif
                            </span>
                            <flux:icon name="arrow-down-tray" class="size-5 shrink-0 opacity-60 transition group-hover:opacity-100" />
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
