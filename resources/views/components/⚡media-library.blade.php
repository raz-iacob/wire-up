<?php

declare(strict_types=1);

use App\Actions\CreateMediaAction;
use App\Actions\DownloadMediaAction;
use App\Enums\MediaType;
use App\Models\Media;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

return new class extends Component
{
    use WithFileUploads;

    public bool $showLibrary = false;

    public ?MediaType $type = null;

    public string $target;

    public int $max = 1;

    /** @var array<int, TemporaryUploadedFile> */
    public array $files = [];

    /** @var Collection<int, Media> */
    public Collection $selected;

    /** @var Collection<int, array<string, mixed>> */
    public Collection $medias;

    public string $search = '';

    public string $typeFilter = '';

    public int $perPage = 20;

    public bool $hasMore = true;

    public bool $showDeleteModal = false;

    public bool $loaded = false;

    public function mount(): void
    {
        $this->selected = collect();
        $this->medias = collect();
    }

    public function updated(string $propertyName): void
    {
        if (in_array($propertyName, ['search', 'typeFilter'], true)) {
            $this->medias = collect();
            $this->loadMedia();
        }
    }

    /** @param ?array<int, Media> $media */
    #[On('select-media')]
    public function handleSelectMedia(string $target, ?string $type = null, int $max = 1, ?array $media = null): void
    {
        $this->target = $target;
        $this->type = MediaType::tryFrom($type);
        $this->typeFilter = $this->type ?? '';
        $this->selected = collect($media ?? []);
        $this->showLibrary = true;
        $this->max = $max;
    }

    public function selectMedia(Media $media, bool $deselect = true): void
    {
        if ($this->isSelected($media->id)) {
            $this->selected = $this->selected
                ->reject(fn ($m): bool => $m->id === $media->id && $deselect)
                ->values();

            return;
        }

        if ($this->max === 1) {
            $this->selected = collect([$media]);

            return;
        }

        if ($this->selected->count() >= $this->max) {
            return;
        }

        $this->selected->push($media);
    }

    public function isSelected(int $mediaId): bool
    {
        return $this->selected->contains(fn (Media $m): bool => $m->id === $mediaId);
    }

    public function clearSelection(): void
    {
        $this->selected = collect();
    }

    public function insertMedia(): void
    {
        $this->dispatch('media-selected', [
            'target' => $this->target,
            'media' => $this->selected,
        ]);
        $this->showLibrary = false;
    }

    public function confirmDelete(): void
    {
        $this->showDeleteModal = true;
    }

    public function deleteCurrentItem(): void
    {
        if ($this->selected->count() !== 0) {
            $this->selected->each(fn (Media $item): bool => $item->delete());
            $this->selected = collect();
            $this->showDeleteModal = false;
            $this->medias = collect();
            $this->loadMedia();
        }
    }

    public function download(DownloadMediaAction $action): StreamedResponse
    {
        return $action->handle($this->selected);
    }

    public function loadMedia(bool $loadMore = false): void
    {
        $base = Media::query()
            ->latest()
            ->when($this->typeFilter, fn ($q, $t) => $q->where('type', $t))
            ->when($this->search, fn ($q, $s) => $q->whereAny(['filename', 'alt_text'], 'like', "%{$s}%")
            );

        $offset = $loadMore ? $this->medias->count() : 0;

        $items = (clone $base)
            ->offset($offset)
            ->limit($this->perPage)
            ->get()
            ->map(fn (Media $m): array => $this->parseMedia($m));

        $this->medias = $this->medias->merge($items);

        $this->hasMore = $this->medias->count() < (clone $base)->count();

        $this->loaded = true;
    }

    public function loadMore(): void
    {
        $this->loadMedia(true);
    }

    #[Renderless]
    public function checkFileExists(string $etag): ?Media
    {
        return Media::query()->where('etag', $etag)->first();
    }

    /** @param array<int, array<string, mixed>> $metadata */
    public function save(array $metadata, CreateMediaAction $action): void
    {
        $rules = ['files.*' => ['max:10240']];

        match ($this->type) {
            MediaType::AUDIO => $rules['files.*'][] = 'mimetypes:audio/mpeg,audio/wav,audio/ogg',
            MediaType::VIDEO => $rules['files.*'][] = 'mimetypes:video/mp4,video/quicktime,video/x-msvideo',
            MediaType::DOCUMENT => $rules['files.*'][] = 'mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            MediaType::IMAGE => $rules['files.*'][] = 'image',
            default => null,
        };

        $validatedFiles = [];

        try {
            $this->validate($rules);

            foreach ($this->files as $index => $file) {
                $validatedFiles[] = [
                    'file' => $file,
                    'metadata' => $metadata[$index] ?? [],
                ];
            }
        } catch (ValidationException $e) {
            $errors = $e->validator->errors();
            foreach ($this->files as $index => $file) {
                if ($errors->has("files.$index")) {
                    Flux::toast(variant: 'danger', heading: __('Upload Failed'),
                        text: __('File ":filename" failed to upload: :error', [
                            'filename' => $file->getClientOriginalName(),
                            'error' => Str::replace('files.'.$index, '', $errors->first("files.$index")),
                        ]), duration: 10000);
                    $file->delete();

                    continue;
                }

                $validatedFiles[] = [
                    'file' => $file,
                    'metadata' => $metadata[$index] ?? [],
                ];
            }
        }

        $this->uploadFiles($validatedFiles, $action);
        $this->medias = collect();
        $this->loadMedia();

        $autoSelectUploadedFiles = Media::query()
            ->latest()
            ->limit(count($validatedFiles))
            ->get();

        foreach ($autoSelectUploadedFiles as $media) {
            if ($this->selected->count() < $this->max) {
                $this->selectMedia($media, false);
            }
        }
    }

    public function stackStyle(int $seed, int $index): string
    {
        mt_srand($seed);
        $top = random_int(1, 3) * ($index % 2 === 0 ? 1 : -1);
        $rotation = random_int(3, 5) * ($index % 2 === 0 ? 1 : -1);
        mt_srand();

        return "style=\"top: {$top}px; z-index: {$index}; transform: rotate({$rotation}deg )\";\"";
    }

    /** @param array<int, array{file: TemporaryUploadedFile, metadata: array<string, mixed>}> $validatedFiles */
    private function uploadFiles(array $validatedFiles, CreateMediaAction $action): void
    {
        foreach ($validatedFiles as ['file' => $file, 'metadata' => $metadata]) {

            $etag = md5_file($file->getRealPath());
            $existing = Media::query()->where('etag', $etag)->first();
            if ($existing) {
                continue;
            }

            $uuid = Str::uuid()->toString();
            $extension = $file->getClientOriginalExtension();
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $filename = $uuid.'_'.Str::slug($originalName).'.'.$extension;
            $path = 'media';

            $file->storeAs($path, $filename, [
                'disk' => config('filesystems.media'),
                'visibility' => 'public',
            ]);

            $thumbnail = null;
            $thumbData = $metadata['thumbnail'] ?? null;

            if ($thumbData && preg_match('/^data:image\/([a-zA-Z0-9]+);base64,/', (string) $thumbData, $matches)) {
                $thumbFilename = $uuid.'_'.Str::slug($originalName).'_thumb.'.$matches[1];

                $base64 = explode(',', (string) $thumbData, 2)[1] ?? null;

                if ($base64) {
                    Storage::disk(config('filesystems.media'))
                        ->put("$path/$thumbFilename", base64_decode($base64), 'public');

                    $thumbnail = "$path/$thumbFilename";
                }
            }

            $action->handle([
                'type' => MediaType::fromMimeType($file->getMimeType())->value,
                'source' => "$path/$filename",
                'etag' => $etag,
                'filename' => $originalName.'.'.$extension,
                'alt_text' => $originalName,
                'mime_type' => $file->getMimeType(),
                'thumbnail' => $thumbnail,
                'size' => $file->getSize(),
                'duration' => $metadata['duration'] ?? null,
                'width' => $metadata['width'] ?? null,
                'height' => $metadata['height'] ?? null,
            ]);
        }

        $this->files = [];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseMedia(Media $media): array
    {
        return [
            'id' => $media->id,
            'preview' => $media->preview,
            'filename' => $media->filename,
            'alt_text' => $media->alt_text,
            'mime_type' => $media->mime_type,
            'thumbnail' => $media->thumbnail,
            'icon' => $media->type->icon(),
            'size' => $media->size,
            'duration' => $media->duration,
            'dimensions' => $media->dimensions,
            'created_at' => $media->created_at->toDateTimeString(),
        ];
    }
};
?>

<div>
    <flux:modal wire:model.self="showLibrary" class="w-full max-w-7xl" :closable="false" :dismissible="false">
        <div class="grid gap-6 md:grid-cols-7">
            <div class="md:col-span-5">
                <div class="flex flex-col md:flex-row gap-4 md:gap-6 justify-between md:items-center mb-6">
                    <flux:heading class="text-lg!">{{ __('Media Library') }}</flux:heading>
                    <div class="flex items-center gap-3">
                        <div class="w-full md:w-52 sm:shrink-0">
                            <flux:select variant="listbox" wire:model.live="typeFilter" :disabled="$type !== null" placeholder="{{ __('Filter by type') }}">
                                <flux:select.option value="">{{ __('All Media') }}</flux:select.option>
                                @foreach(MediaType::cases() as $type)
                                <flux:select.option value="{{ $type->value }}">{{ $type->label(true) }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        <div class="w-full md:w-52 sm:shrink-0">
                            <flux:input icon="magnifying-glass" wire:model.live="search" placeholder="{{ __('Search...') }}" clearable />
                        </div>
                    </div>
                </div>
                
                <flux:file-upload multiple>
                    <flux:file-upload.dropzone
                        heading="{{ __('Drop files or click to browse') }}"
                        text="{{ __('JPG, PNG, GIF up to 10MB') }}"
                        with-progress
                        inline
                        class="justify-center"
                    />
                </flux:file-upload>

                <div class="mt-6 grid content-start min-h-100 overflow-y-auto overscroll-contain p-2 select-none md:h-[calc(100vh-22rem)] grid-cols-2  md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4" wire:loading.class="opacity-60 animate-pulse" wire:target="loadMore">
                    
                    @if(!$loaded)
                        @for($i = 0; $i < 10; $i++)
                        <flux:skeleton animate="shimmer" class="size-full aspect-square rounded-sm" wire:loading />
                        @endfor
                    @endif

                    @forelse ($medias as $media)
                    <div class="relative">
                        <img wire:key="media-{{ $media['id'] }}"
                            src="{{ $media['preview'] }}" alt="{{ $media['alt_text'] }}"
                            class="w-full aspect-square object-contain bg-black/10 dark:bg-white/5 border border-zinc-200 dark:border-white/20 cursor-pointer rounded-sm data-selected:outline-2 data-selected:outline-offset-2 data-selected:outline-sky-500 dark:data-selected:outline-sky-600 data-selected:opacity-75"
                            wire:click="selectMedia({{ $media['id'] }})"
                            loading="lazy"
                            @if($this->isSelected($media['id'])) data-selected @endif
                        />
                        @if($media['icon'] !== 'photo')
                        <div class="absolute bottom-2 right-2 bg-black/60 rounded-full p-1">
                            <flux:icon name="{{ $media['icon'] }}" class="size-4 text-white" />
                        </div>
                        @if(!$media['thumbnail'])
                        <div class="absolute top-1/2 -translate-y-1/2 left-1/2 -translate-x-1/2 text-center pointer-events-none px-2 w-full">
                            <flux:text class="text-sm break-all" variant="subtle">{{ $media['filename'] }}</flux:text>
                        </div>
                        @endif
                        @endif
                    </div>
                    @empty

                    @if($loaded)
                    <div class="col-span-full text-center py-10">
                        <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('No media found.') }}</flux:text>
                    </div>
                    @endif
                    
                    @endforelse

                    @if($hasMore)
                    <div wire:intersect.margin.100px="loadMore"></div>
                    @endif
                </div>
            </div>

            <div class="md:col-span-2 md:border-l md:border-zinc-200 md:dark:border-zinc-700 md:pl-6 flex flex-col gap-4 h-full relative">
                @if($selected->count() === 0)
                <div wire:loading wire:target="selectMedia" class="grow">
                    <div class="w-full aspect-video mb-8">
                        <flux:skeleton animate="shimmer" class="size-full rounded-sm" />
                    </div>
                    <flux:skeleton.group animate="shimmer" class="space-y-6">
                        <div>
                            <flux:skeleton class="h-6 w-9/12 mb-1" />
                            <flux:skeleton.line class="w-1/6" />
                        </div>
                        <flux:skeleton class="h-10 w-2/5 rounded-lg" />
                        <div>
                            <flux:heading>{{ __('Information') }}</flux:heading>
                            <div class="my-2 h-px bg-zinc-200 dark:bg-zinc-700"></div>
                            <div class="flex items-center justify-between">
                                <flux:text class="text-xs">{{ __('Type') }}</flux:text>
                                <flux:skeleton.line class="w-1/4" />
                            </div>
                            <div class="my-2 h-px bg-zinc-200 dark:bg-zinc-700"></div>
                            <div class="flex items-center justify-between">
                                <flux:text class="text-xs">{{ __('Uploaded at') }}</flux:text>
                                <flux:skeleton.line class="w-1/4" />
                            </div>
                        </div>
                    </flux:skeleton.group>
                </div>
                @endif
                
                @if($selected->count() > 0)
                    @foreach ($selected as $media)
                    <div wire:key="selected-media-{{ $media->id }}" class="flex items-center gap-4 w-full bg-zinc-100 dark:bg-zinc-700 border border-zinc-300 dark:border-zinc-800 rounded-sm overflow-hidden {{ $loop->index > 0 ? 'absolute shadow-lg scale-93 origin-center left-1/2 -translate-x-1/2' : 'relative' }}" {!! $loop->index > 0 ? $this->stackStyle($media->id, $loop->index) : '' !!}>
                        <img src="{{ $media->preview }}" alt="{{ $media->alt_text }}" class="w-full aspect-video object-contain" />
                        @if($media->type->icon() !== 'photo' && !$media->thumbnail)
                        <div class="absolute top-1/2 -translate-y-1/2 left-1/2 -translate-x-1/2 text-center pointer-events-none px-2 w-full">
                            <flux:text class="text-sm break-all" variant="subtle">{{ $media->filename }}</flux:text>
                        </div>
                        @endif
                    </div>
                    @endforeach

                    <div class="grow mt-4 space-y-6">
                        @if($selected->count() === 1 && $firstMedia = $selected->first())
                            <div>
                                <flux:heading size="lg" class="truncate" title="{{ $firstMedia->filename }}">{{ $firstMedia->alt_text }}</flux:heading>
                                <flux:text>{{ Number::fileSize($firstMedia->size) }}</flux:text>
                            </div>

                            <flux:button.group>
                                <flux:button icon="pencil" square tooltip="{{ __('Edit') }}" x-on:click="$flux.modal('edit-digital-item').show()"></flux:button>
                                <flux:button icon="arrow-down-tray" square tooltip="{{ __('Download') }}" wire:click="download"></flux:button>
                                <flux:button icon="trash" square tooltip="{{ __('Delete') }}" wire:click="confirmDelete"></flux:button>
                            </flux:button.group>

                            <div>
                                <flux:heading>{{ __('Information') }}</flux:heading>
                                <div class="my-2 h-px bg-zinc-200 dark:bg-zinc-700"></div>
                                <div class="flex items-center justify-between">
                                    <flux:text class="text-xs">{{ __('Type') }}</flux:text>
                                    <flux:text class="text-xs">{{ $firstMedia->type->label() }}</flux:text>
                                </div>
                                @if($firstMedia->dimensions)
                                <div class="my-2 h-px bg-zinc-200 dark:bg-zinc-700"></div>
                                <div class="flex items-center justify-between">
                                    <flux:text class="text-xs">{{ __('Dimensions') }}</flux:text>
                                    <flux:text class="text-xs">{{ $firstMedia->dimensions }}</flux:text>
                                </div>
                                @endif
                                @if($firstMedia->duration)
                                <div class="my-2 h-px bg-zinc-200 dark:bg-zinc-700"></div>
                                <div class="flex items-center justify-between">
                                    <flux:text class="text-xs">{{ __('Duration') }}</flux:text>
                                    <flux:text class="text-xs">{{ gmdate('H:i:s', $firstMedia->duration) }}</flux:text>
                                </div>
                                @endif
                                <div class="my-2 h-px bg-zinc-200 dark:bg-zinc-700"></div>
                                <div class="flex items-center justify-between">
                                    <flux:text class="text-xs">{{ __('Uploaded at') }}</flux:text>
                                    <flux:text class="text-xs">{{ $firstMedia->created_at?->format('j M Y, h:i A') }}</flux:text>
                                </div>
                            </div>
                        @else
                            <div>
                                <flux:heading size="lg" class="truncate">{{ $selected->count() }} {{ __('Items selected') }}</flux:heading>
                                <flux:text>{{ Number::fileSize($selected->sum('size')) }}</flux:text>
                            </div>

                            <flux:button.group>
                                <flux:button icon="x-mark" square tooltip="{{ __('Clear') }}" wire:click="clearSelection"></flux:button>
                                <flux:button icon="arrow-down-tray" square tooltip="{{ __('Download') }}" wire:click="download"></flux:button>
                                <flux:button icon="trash" square tooltip="{{ __('Delete') }}" wire:click="confirmDelete"></flux:button>
                            </flux:button.group>
                        @endif
                    </div>
                @else
                    <div class="grow" wire:loading.remove wire:target="selectMedia">
                        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ $max > 1 ? __('No files selected.') : __('No file selected.') }}</flux:text>
                    </div>
                @endif

                <div class="flex justify-between items-center gap-4 mt-6">
                    <flux:button wire:click="$set('showLibrary', false)" class="w-full">
                        {{ __('Cancel') }}
                    </flux:button>
                    @if($selected->count() > 0)
                    <flux:button variant="primary" wire:click="insertMedia" class="w-full">
                        {{ __('Insert') }}
                    </flux:button>
                    @endif
                </div>

                @error('files')
                    <div x-data x-init="$flux.toast({ variant: 'danger', heading: '{{ __('Upload Failed') }}', text: '{{ __('Try a smaller file or different format.') }}', duration: 10000 })"></div>
                @enderror
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model.self="showDeleteModal" class="min-w-88">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $selected->count() > 1 ? __('Delete files?') : __('Delete file?') }}</flux:heading>
                <flux:text class="mt-2">
                    @if($selected->count() > 1)
                    {{ __('You\'re about to delete :count files.', ['count' => $selected->count()]) }}
                    @else
                    {{ __('You\'re about to delete the file :filename', ['filename' => $selected->first()?->filename]) }}
                    @endif
                    <br>{{ __('Are you sure? This action cannot be reversed.') }}
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="deleteCurrentItem">{{ __('Yes, Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>

<script>
    this.el.querySelector('input[type="file"]').addEventListener('change', async (e) => {
        await uploadFiles(e.target.files);
    });

    this.el.querySelector('[data-flux-file-upload]').addEventListener('drop', async (e) => {
        await uploadFiles(e.dataTransfer.files);
    });

    const uploadFiles = async (files) => {
        const media = [];
        const metadata = [];

        for (let i = 0; i < files.length; i++) {
            let etag = await window.fileMd5(files[i]);
            let exists = await this.call('checkFileExists', etag);
            if (exists) {
                this.call('selectMedia', exists, false);
                continue;
            }

            media.push(files[i]);
            metadata.push(await analyzeFile(files[i]));
        }
        
        if(media.length === 0) {
            return;
        }

        const dropzone = this.el.querySelector('[data-flux-file-upload-dropzone]');
        dropzone?.setAttribute('data-loading', '');

        let totalProgress = 0;
        let fileCount = media.length;

        this.uploadMultiple('files', media, (uploadedFilename) => {
            dropzone?.removeAttribute('data-loading');
            this.call('save', metadata);
        }, (error) => {
            dropzone?.removeAttribute('data-loading');
            console.log('Upload error', error);
        }, (event) => {
            const currentFileProgress = event.detail.progress || 0;
            totalProgress = (totalProgress + currentFileProgress) / fileCount;
            
            const displayProgress = Math.floor(Math.min(totalProgress, 99));
            updateProgress(displayProgress);
            console.log('Cumulative Progress:', displayProgress);
        });
    };

    const updateProgress = (progress) => {
        const root = this.el;
        root.style.setProperty('--flux-file-upload-progress', `${progress}%`);
        root.style.setProperty('--flux-file-upload-progress-as-string', `"${progress}%"`);
    };
</script>