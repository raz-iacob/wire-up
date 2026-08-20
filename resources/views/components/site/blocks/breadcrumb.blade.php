@props(['block'])

@php
    $content = $block->content ?? [];
    $hasBg = (bool) ($content['hasBackground'] ?? false);
    $for = $page ?? null;

    $trail = $for instanceof \App\Models\Page || $for instanceof \App\Models\Record
        ? \App\Services\BreadcrumbService::current()->trail(
            $for,
            (bool) ($content['showHome'] ?? true),
            $block->plainTextField('homeLabel'),
        )
        : [];
@endphp

@if (count($trail) > 1)
    <section @class([
        'w-full',
        'bg-(--wire-card-bg) text-(--wire-card-text)' => $hasBg,
        ($pad ?? 'py-16') => $hasBg,
    ])>
        <div class="mx-auto max-w-(--wire-container) px-(--wire-gutter)">
            <x-site.breadcrumbs
                :trail="$trail"
                :align="$content['align'] ?? 'center'"
                :separator="$content['separator'] ?? '/'"
            />
        </div>
    </section>
@endif
