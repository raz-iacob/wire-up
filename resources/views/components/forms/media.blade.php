@props([
    'name' => 'cover', 
    'type' => null, 
    'locale' => 'en', 
    'multiLocale' => false, 
    'label' => null, 
    'note' => null,
    'multiple' => false,
    'max' => 30,
])

@php
$button = match($type) {
    'image' => $multiple ? __('Attach Images') : __('Attach Image'),
    'video' => $multiple ? __('Attach Videos') : __('Attach Video'),
    'audio' => $multiple ? __('Attach Audios') : __('Attach Audio'),
    'document' => $multiple ? __('Attach Files') : __('Attach File'),
    default => __('Attach Media'),
};
@endphp

<div wire:key="{{ $name }}-{{ $locale }}" x-data="mediaSelector{{ ucfirst($name) }}($wire, '{{ $name }}', '{{ $locale }}')">
    <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-3">
        <div class="flex items-center gap-3">
            @if($label)
            <flux:label>{{ $label }}</flux:label>
            @endif

            @if($multiLocale)
            <flux:tooltip content="{{ __('Change language') }}">
                <flux:badge size="sm" class="text-xs py-0.5!" as="button" x-on:click="$wire.dispatch('change-locale')">{{ strtoupper($locale) }}</flux:badge>
            </flux:tooltip>
            @endif
        </div>

        @if($note)
        <flux:subheading>{{ $note }}</flux:subheading>
        @endif
    </div>

    <flux:card class="rounded-lg block disabled:shadow-none dark:shadow-none appearance-none text-base sm:text-sm p-2 bg-white dark:bg-white/10 shadow-xs border-zinc-200 border-b-zinc-300/80 disabled:border-b-zinc-200 dark:border-white/10 dark:disabled:border-white/5">
        <template x-if="media">
        <div class="flex flex-col md:flex-row justify-between gap-4 mb-6">
            <div class="flex flex-col md:flex-row gap-4 grow">
                <div
                    wire:click="$dispatch('select-media', { target: '{{ $name }}.{{ $locale }}.0', type: '{{ $type }}', media })">
                    @if($type === 'image')
                    <img :src="media.url" alt="{{ $name }}" id="{{ $name }}Preview" class="w-40 h-40 bg-black/40 dark:bg-white/10 border border-zinc-200 dark:border-white/20 cursor-pointer hover:opacity-85 object-contain" />
                    @elseif($type === 'video')
                    <div>
                        <div
                            class="w-40 relative bg-black/10 dark:bg-white/10 border border-zinc-200 dark:border-white/20" x-show="media?.encoded?.status == 'COMPLETE'" x-cloak>
                            <img class="w-full cursor-pointer hover:opacity-85" :src="Object.values(media?.encoded?.previews ?? {}).find((preview) => preview.active)?.url" alt="{{ $name }}" />
                            <flux:icon.play
                                class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 text-white" />
                        </div>
                        <div class="w-40 aspect-video bg-black/10 dark:bg-white/10 border border-zinc-200 dark:border-white/20 animate-pulse flex justify-center items-center cursor-pointer hover:opacity-85" x-show="progress && progress < 101" x-cloak>
                            <flux:text size="sm">Processing <span x-text="progress"></span>%</flux:text>
                        </div>
                    </div>
                    @elseif($type === 'audio')
                    <div class="w-40 aspect-video relative bg-black/10 dark:bg-white/10 border border-zinc-200 dark:border-white/20 cursor-pointer hover:opacity-85">
                        <flux:icon.musical-note
                                class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 text-zinc-800 dark:text-white" />
                    </div>
                    @else
                    <div class="w-40 aspect-video relative bg-black/10 dark:bg-white/10 border border-zinc-200 dark:border-white/20 cursor-pointer hover:opacity-85">
                        <flux:icon.document 
                            class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 text-zinc-800 dark:text-white" />
                    </div>
                    @endif
                </div>
                
                <div>
                    <flux:heading x-text="media?.name"></flux:heading>
                    @if($type === 'image')
                    <flux:subheading>
                        Original: <span x-text="media?.dimensions"></span>
                    </flux:subheading>
                    <flux:subheading>
                        Crop: <span x-text="`${media?.crop?.w} x ${media?.crop?.h}`"></span>
                    </flux:subheading>
                    @elseif($type === 'video' || $type === 'audio')
                    <flux:subheading>
                        Duration: <span x-text="media?.duration"></span>
                    </flux:subheading>
                    @endif
                </div>
            </div>
            <div>
                <flux:button.group>
                    <flux:button icon="arrow-path-rounded-square" tooltip="Change" square x-on:click="$wire.$dispatch('select-media', { type: '{{ $type }}', role: ['{{ $name }}', '{{ $locale }}', 0], locale: '{{ $locale }}', mode: 'select', selected: media }); $flux.modal('media-library').show();"></flux:button>
                    <flux:button icon="crop" tooltip="Crop" square x-on:click="$flux.modal('cropper{{ ucfirst($name) }}').show(); showCropper()" x-cloak x-show="media.type === 'image'"></flux:button>
                    <template x-if="media.type === 'video' && media.encoded?.status === 'COMPLETE'">
                        <flux:button icon="play" tooltip="Play" square x-on:click="$flux.modal('preview{{ ucfirst($name) }}').show()" x-cloak></flux:button>
                    </template>
                    <flux:button icon="x-mark" tooltip="Remove" square x-on:click="removeMedia"></flux:button>
                </flux:button.group>
            </div>
        </div>
        </template>

        <template x-if="!media">
            <flux:button variant="filled" wire:click="$dispatch('select-media', { target: '{{ $name }}.{{ $locale }}.0', type: {{ $type ? '\''.$type.'\'' : 'null' }}, max: {{ $multiple ? $max : 1 }} })">{{ $button }}</flux:button>
        </template>
    </flux:card>

    {{-- @if($type === 'image')
    <flux:modal name="cropper{{ ucfirst($name) }}" class="w-full max-w-4xl" x-on:close="hideCropper" :dismissible="false">
        <div class="mb-8">
            <flux:heading size="lg">Edit image crop</flux:heading>
        </div>
        <div class="flex h-[400px] justify-center items-center bg-zinc-100 dark:bg-zinc-800 mb-8">
            <div class="relative h-full w-full">
                <img id="{{ $name }}Image" :src="media?.sourceUrl" class="block max-w-full" style="display:none" />
            </div>
        </div>
        <div class="flex justify-between items-center">
            <flux:modal.close>
                <flux:button type="button" variant="primary" x-on:click="updateCrop">Update</flux:button>
            </flux:modal.close>
            <flux:text x-text="`${crop.w} x ${crop.h}`"></flux:text>
        </div>
    </flux:modal>
    @endif

    @if($type === 'video')
    <flux:modal name="preview{{ ucfirst($name) }}" class="w-full max-w-4xl" x-on:close="hidePreview" :dismissible="false">
        <div class="mb-6">
            <flux:heading size="lg">Preview</flux:heading>
        </div>
        <template x-if="media && media.encoded?.status === 'COMPLETE'">
            <iframe :src="`/admin/media/preview/${media.id}`" class="aspect-video w-full">Loading...</iframe>
        </template>
    </flux:modal>
    @endif --}}
</div>

<script>
    function mediaSelector{{ ucfirst($name) }}($wire, name, locale) {
        return {
            edit: null,
            cropper: null,
            crop: {},
            refresh: 0,
            progress: false,
            media: $wire[name]?.[locale],
            listeners: [],
            key: `${name}.${locale}`,
            showCropper() {
                const image = document.getElementById(name + "Image")
                const preview = document.getElementById(name + "Preview")
                this.cropper = new Cropper(image, {
                    viewMode: 1,
                    movable: false,
                    rotatable: false,
                    zoomable: false,
                    scalable: false,
                    background: false,
                    minContainerHeight: 400,
                    minCropBoxWidth: 100,
                    minCropBoxHeight: 100,
                    data: {
                        width: this.media.crop.w,
                        height: this.media.crop.h,
                        x: this.media.crop.x,
                        y: this.media.crop.y,
                    },
                    responsive: true,
                    dragMode: "move",
                    autoCropArea: 1,
                    crop: (e) => {
                        this.crop = {
                            w: Math.round(e.detail.width),
                            h: Math.round(e.detail.height),
                            x: Math.round(e.detail.x),
                            y: Math.round(e.detail.y),
                        }
                    },
                })
            },
            hideCropper() {
                this.cropper.destroy()
            },
            updateCrop() {
                this.media.url = this.cropper
                    .getCroppedCanvas()
                    .toDataURL()
                this.cropper.destroy()
                this.media.crop = this.crop
            },
            hidePreview() {
                // multiple iframes
                const iframes = document.querySelectorAll("iframe")
                iframes.forEach((iframe) => {
                    iframe.contentWindow.postMessage("pause", "*")
                })
            },
            removeMedia() {
                if (this.media) {
                    delete [name]?.[locale]?.[idx]
                    this.media = null
                }
            },
            init() {
                this.listeners.push(
                    Livewire.on("media-selected", (event) => {
                        if (event.for !== this.key) {
                            return
                        }
                        this.media = event.media
                        $wire.$set(event.for, event.media)
                    }),
                )

                this.listeners.push(
                    Livewire.on("media-progress", (event) => {
                        if (event.for !== this.key) {
                            return
                        }
                        this.progress = event.progress
                    }),
                )

                this.listeners.push(
                    Livewire.on("media-updated", (event) => {
                        if (event.for !== this.key) {
                            return
                        }
                        this.media = event.media
                        $wire.$set(event.for, event.media)
                    }),
                )

                this.listeners.push(
                    Livewire.on("media-deleted", (event) => {
                        if (event.for !== this.key) {
                            return
                        }
                        this.media = null
                        $wire.$set(event.for, null)
                    }),
                )
            },
            destroy() {
                this.listeners.forEach((listener) => listener())
            },
        }
    }
</script>