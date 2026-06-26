@props(['block'])

@php
    $content = $block->content ?? [];
    $hasBg = (bool) ($content['hasBackground'] ?? false);
    $config = $content;
    unset($config['recipient']);
    $pageId = $block->blockable_type === 'page' ? $block->blockable_id : null;
    $key = $block->id ?? md5((string) json_encode($content));
@endphp

<section
    @class([
        'w-full',
        'bg-(--wire-card-bg) text-(--wire-card-text)' => $hasBg,
        ($pad ?? 'py-16') => $hasBg,
    ])
>
    <div class="mx-auto max-w-(--wire-container) px-(--wire-gutter)">
        <livewire:site.contact-form
            :config="$config"
            :block-id="$block->id"
            :page-id="$pageId"
            wire:key="contact-form-{{ $key }}" />
    </div>
</section>
