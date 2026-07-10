@props(['block'])

@php
    $content = $block->content ?? [];
    $blockKey = (string) ($block->id ?? md5((string) json_encode($content)));
@endphp

<livewire:site.record-search
    :block-id="$blockKey"
    :pad="$pad ?? 'py-16'"
    :content="$content"
    :heading="$block->text('heading')"
    :placeholder="$block->text('placeholder')"
    wire:key="search-{{ $blockKey }}" />
