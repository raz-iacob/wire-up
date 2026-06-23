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
    class="w-full py-18"
    @if ($hasBg) style="background-color:var(--wire-card-bg);color:var(--wire-card-text)" @endif
>
    <div class="mx-auto max-w-7xl px-6">
        <livewire:site.contact-form
            :config="$config"
            :block-id="$block->id"
            :page-id="$pageId"
            wire:key="contact-form-{{ $key }}" />
    </div>
</section>
