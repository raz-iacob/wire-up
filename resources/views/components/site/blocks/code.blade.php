@props(['block'])

@php
    $content = $block->content ?? [];
    $code = (string) ($content['code'] ?? '');
    $language = (string) ($content['language'] ?? 'plaintext');
    $language = preg_match('/^[a-z0-9]+$/', $language) === 1 ? $language : 'plaintext';
    $filename = mb_trim((string) ($content['filename'] ?? ''));
    $wrap = (bool) ($content['wrap'] ?? false);
    $hasBg = (bool) ($content['hasBackground'] ?? false);
    $heading = $block->text('heading');
    $intro = $block->text('intro');
    $hasHeading = strip_tags($heading) !== '' || strip_tags($intro) !== '';
@endphp

@if ($code !== '' || $hasHeading)
    <section @class([
        'w-full',
        'bg-(--wire-card-bg) text-(--wire-card-text)' => $hasBg,
        ($pad ?? 'py-16') => $hasBg,
    ])>
        <div class="mx-auto max-w-(--wire-container) px-(--wire-gutter)">
            @if ($hasHeading)
                <div class="mb-8">
                    @if (strip_tags($heading) !== '')
                        <div class="tracking-tight [&>p]:m-0 [&_a]:text-(--wire-accent) [&_a]:underline text-(length:--wire-heading-size)">{!! $heading !!}</div>
                    @endif
                    @if (strip_tags($intro) !== '')
                        <div class="mt-3 leading-relaxed opacity-80 [&_a]:text-(--wire-accent) [&_a]:underline [&>p]:my-2 *:first:mt-0 *:last:mb-0">{!! $intro !!}</div>
                    @endif
                </div>
            @endif

            @if ($code !== '')
                <figure class="wire-code group relative" x-data="{ copied: false }">
                    @if ($filename !== '')
                        <figcaption class="wire-code-bar">{{ $filename }}</figcaption>
                    @endif

                    <button
                        type="button"
                        class="wire-code-copy"
                        aria-label="{{ __('Copy code') }}"
                        x-on:click="navigator.clipboard.writeText($refs.code.textContent); copied = true; setTimeout(() => copied = false, 1600)"
                        x-text="copied ? @js(__('Copied')) : @js(__('Copy'))"
                    >{{ __('Copy') }}</button>

                    <pre @class(['wire-code-pre', 'whitespace-pre-wrap break-words' => $wrap, 'overflow-x-auto' => ! $wrap])><code x-ref="code" data-highlight class="language-{{ $language }}">{{ $code }}</code></pre>
                </figure>
            @endif
        </div>
    </section>

    @once
        @vite('resources/js/code.js')
    @endonce
@endif
