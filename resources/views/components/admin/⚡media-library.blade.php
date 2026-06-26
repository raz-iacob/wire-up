<?php

declare(strict_types=1);

use App\Actions\CreateMediaAction;
use App\Actions\DownloadMediaAction;
use App\Actions\ImportPexelsMediaAction;
use App\Actions\UpdateMediaAction;
use App\Enums\MediaType;
use App\Models\Media;
use App\Services\PexelsService;
use App\Services\UploadLimit;
use enshrined\svgSanitize\Sanitizer;
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

    /**
     * @var array<int, string>
     */
    public array $allowedTypes = [];

    public string $target = '';

    public int $max = 1;

    /** @var array<int, TemporaryUploadedFile> */
    public array $files = [];

    /**
     * @var array<int, int>
     */
    public array $selectedIds = [];

    /** @var Collection<int, Media> */
    public Collection $selected;

    /** @var Collection<int, array<string, mixed>> */
    public Collection $medias;

    public string $search = '';

    public string $typeFilter = '';

    public int $perPage = 20;

    public bool $hasMore = true;

    public bool $showEditModal = false;

    public string $altText = '';

    public bool $showDeleteModal = false;

    public bool $loaded = false;

    public bool $pexelsMode = false;

    public string $pexelsQuery = '';

    public string $pexelsKind = 'image';

    public int $pexelsPage = 1;

    public bool $pexelsHasMore = false;

    public bool $pexelsLoaded = false;

    /** @var array<int, array<string, mixed>> */
    public array $pexelsResults = [];

    /** @var array<string, mixed>|null */
    public ?array $pexelsPreview = null;

    public function mount(): void
    {
        $this->selected = collect();
        $this->medias = collect();
    }

    public function hydrate(): void
    {
        $this->selected = $this->selectionFromIds($this->selectedIds);
    }

    public function updated(string $propertyName): void
    {
        if (in_array($propertyName, ['search', 'typeFilter'], true)) {
            $this->medias = collect();
            $this->loadMedia();
        }
    }

    /** @param ?array<int, array<string, mixed>|Media> $media */
    #[On('select-media')]
    public function handleSelectMedia(string $target, ?string $type = null, int $max = 1, ?array $media = null): void
    {
        $types = collect(explode(',', (string) $type))
            ->map(fn (string $value): ?MediaType => MediaType::tryFrom(mb_trim($value)))
            ->filter()
            ->values();

        $this->target = $target;
        $this->allowedTypes = $types->map(fn (MediaType $mediaType): string => $mediaType->value)->all();
        $this->type = $types->count() === 1 ? $types->first() : null;
        $this->typeFilter = $this->type->value ?? '';
        $this->syncSelected($this->hydrateSelection($media ?? []));
        $this->showLibrary = true;
        $this->max = $max;

        $this->resetPexels();

        $this->medias = collect();
        $this->loadMedia();
    }

    /**
     * @param  array<int, array<string, mixed>|Media>  $media
     * @return Collection<int, Media>
     */
    private function hydrateSelection(array $media): Collection
    {
        $ids = collect($media)
            ->map(fn (array|Media $item): ?int => $item instanceof Media ? $item->id : (isset($item['id']) ? (int) $item['id'] : null))
            ->filter()
            ->values()
            ->all();

        return $this->selectionFromIds($ids);
    }

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, Media>
     */
    private function selectionFromIds(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return Media::query()
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Media $item): int => (int) array_search($item->id, $ids, true))
            ->values();
    }

    /**
     * @param  Collection<int, Media>  $selected
     */
    private function syncSelected(Collection $selected): void
    {
        $this->selected = $selected->values();
        $this->selectedIds = $selected->pluck('id')->all();
    }

    public function selectMedia(Media $media, bool $deselect = true): void
    {
        if ($this->isSelected($media->id)) {
            if ($deselect) {
                $this->syncSelected($this->selected->reject(fn (Media $m): bool => $m->id === $media->id));
            }

            return;
        }

        if ($this->max === 1) {
            $this->syncSelected(collect([$media]));

            return;
        }

        if ($this->selected->count() >= $this->max) {
            return;
        }

        $this->syncSelected($this->selected->push($media));
    }

    public function selectMediaById(int $mediaId): void
    {
        $media = Media::query()->find($mediaId);
        if ($media) {
            $this->selectMedia($media);
        }
    }

    /**
     * @param array<int, int> $mediaIds
     */
    public function selectMediaRange(array $mediaIds): void
    {
        if ($mediaIds === []) {
            return;
        }

        if ($this->max === 1) {
            $lastId = end($mediaIds);
            if ($lastId) {
                $media = Media::query()->find($lastId);
                if ($media) {
                    $this->selectMedia($media);
                }
            }
            return;
        }

        $newMedia = Media::query()->whereIn('id', $mediaIds)->get();

        $selected = $this->selected;

        foreach ($newMedia as $media) {
            if ($selected->count() >= $this->max) {
                break;
            }

            if ($selected->doesntContain(fn (Media $m): bool => $m->id === $media->id)) {
                $selected->push($media);
            }
        }

        $this->syncSelected($selected);
    }

    public function isSelected(int $mediaId): bool
    {
        return $this->selected->contains(fn (Media $m): bool => $m->id === $mediaId);
    }

    public function clearSelection(): void
    {
        $this->syncSelected(collect());
    }

    public function insertMedia(): void
    {
        $this->dispatch(
            'media-selected',
            target: $this->target,
            media: $this->selected
                ->map(fn (Media $media): array => $this->parseMedia($media))
                ->values()
                ->all(),
        );
        $this->showLibrary = false;
    }

    public function edit(): void
    {
        if ($this->selected->count() !== 1) {
            return;
        }

        $this->altText = $this->selected->first()->alt_text;
        $this->showEditModal = true;
    }

    public function update(UpdateMediaAction $action): void
    {
        if ($this->selected->count() !== 1) {
            return;
        }

        $media = $this->selected->first();
        $action->handle($media, [
            'alt_text' => $this->altText,
        ]);

        $this->syncSelected(collect([$media]));
        $this->medias = collect();
        $this->loadMedia();
        $this->showEditModal = false;
    }

    public function confirmDelete(): void
    {
        $this->showDeleteModal = true;
    }

    public function deleteCurrentItem(): void
    {
        if ($this->selected->count() === 0) {
            return;
        }

        $blocked = $this->selected->reject(fn (Media $item): bool => $item->delete())->values();

        $this->showDeleteModal = false;
        $this->medias = collect();
        $this->loadMedia();

        if ($blocked->isNotEmpty()) {
            Flux::toast(
                variant: 'warning',
                heading: __('Media in use'),
                text: $blocked->count() > 1
                    ? __(':count files couldn\'t be deleted because they\'re still in use.', ['count' => $blocked->count()])
                    : __('":name" couldn\'t be deleted because it\'s still in use.', ['name' => $blocked->first()->filename]),
                duration: 8000,
            );
        }

        $this->syncSelected($blocked);
    }

    /**
     * @return array<int, array{id: int, filename: ?string, labels: array<int, string>}>
     */
    public function selectedUsages(): array
    {
        return $this->selected
            ->map(fn (Media $media): array => [
                'id' => $media->id,
                'filename' => $media->filename,
                'labels' => $media->usageLabels(),
            ])
            ->all();
    }

    public function download(DownloadMediaAction $action): StreamedResponse
    {
        return $action->handle($this->selected);
    }

    public function loadMedia(bool $loadMore = false): void
    {
        $base = Media::query()
            ->latest()
            ->when($this->allowedTypes !== [], fn ($q) => $q->whereIn('type', $this->allowedTypes))
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

    public function pexelsEnabled(): bool
    {
        return resolve(PexelsService::class)->configured();
    }

    public function pexelsSupported(): bool
    {
        return $this->pexelsAllowedKinds() !== [];
    }

    public function pexelsHasBothKinds(): bool
    {
        return count($this->pexelsAllowedKinds()) === 2;
    }

    public function togglePexels(): void
    {
        $this->pexelsMode = ! $this->pexelsMode;

        if (! $this->pexelsMode) {
            return;
        }

        $this->pexelsKind = $this->pexelsAllowedKinds()[0] ?? MediaType::IMAGE->value;
        $this->pexelsQuery = '';
        $this->pexelsPreview = null;
        $this->searchPexels();
    }

    public function searchPexels(bool $loadMore = false): void
    {
        if (! $this->pexelsEnabled() || ! $this->pexelsSupported()) {
            return;
        }

        if (! $loadMore) {
            $this->pexelsPreview = null;
        }

        $service = resolve(PexelsService::class);
        $this->pexelsPage = $loadMore ? $this->pexelsPage + 1 : 1;

        $data = $this->pexelsKind === MediaType::VIDEO->value
            ? $service->searchVideos($this->pexelsQuery, $this->pexelsPage)
            : $service->searchPhotos($this->pexelsQuery, $this->pexelsPage);

        $this->pexelsResults = $loadMore
            ? array_merge($this->pexelsResults, $data['results'])
            : $data['results'];
        $this->pexelsHasMore = $data['hasMore'];
        $this->pexelsLoaded = true;
    }

    public function updatedPexelsQuery(): void
    {
        $this->searchPexels();
    }

    public function updatedPexelsKind(): void
    {
        $this->pexelsResults = [];
        $this->pexelsLoaded = false;
        $this->pexelsPreview = null;
        $this->searchPexels();
    }

    public function loadMorePexels(): void
    {
        $this->searchPexels(true);
    }

    public function previewPexels(int $id): void
    {
        $this->pexelsPreview = collect($this->pexelsResults)->firstWhere('id', $id);
    }

    public function importFromPexels(int $id, ImportPexelsMediaAction $action): void
    {
        $item = collect($this->pexelsResults)->firstWhere('id', $id);

        if ($item === null) {
            return;
        }

        try {
            $media = $action->handle($item);
        } catch (\Throwable) {
            Flux::toast(
                variant: 'danger',
                heading: __('Import Failed'),
                text: __('We couldn\'t import that file from Pexels. Please try again.'),
                duration: 8000,
            );

            return;
        }

        $this->pexelsMode = false;
        $this->pexelsPreview = null;
        $this->medias = collect();
        $this->loadMedia();
        $this->selectMedia($media, false);
    }

    /**
     * @return array<int, string>
     */
    private function pexelsAllowedKinds(): array
    {
        $supported = [MediaType::IMAGE->value, MediaType::VIDEO->value];

        if ($this->allowedTypes === []) {
            return $supported;
        }

        return array_values(array_intersect($supported, $this->allowedTypes));
    }

    private function resetPexels(): void
    {
        $this->pexelsMode = false;
        $this->pexelsQuery = '';
        $this->pexelsPage = 1;
        $this->pexelsResults = [];
        $this->pexelsPreview = null;
        $this->pexelsHasMore = false;
        $this->pexelsLoaded = false;
    }

    #[Renderless]
    public function checkFileExists(string $etag): ?Media
    {
        return Media::query()->where('etag', $etag)->first();
    }

    public function maxImageKilobytes(): int
    {
        return UploadLimit::cappedKilobytes(UploadLimit::IMAGE_MAX_KILOBYTES);
    }

    public function maxVideoKilobytes(): int
    {
        return UploadLimit::cappedKilobytes(UploadLimit::VIDEO_MAX_KILOBYTES);
    }

    /** @param array<int, array<string, mixed>> $metadata */
    public function save(array $metadata, CreateMediaAction $action): void
    {
        $allowsVideo = $this->type === MediaType::VIDEO || in_array(MediaType::VIDEO->value, $this->allowedTypes, true);
        $maxKilobytes = UploadLimit::enforcedKilobytes($allowsVideo ? UploadLimit::VIDEO_MAX_KILOBYTES : UploadLimit::IMAGE_MAX_KILOBYTES);

        $rules = ['files.*' => ['max:'.$maxKilobytes]];

        if ($this->type instanceof MediaType) {
            match ($this->type) {
                MediaType::AUDIO => $rules['files.*'][] = 'mimetypes:audio/mpeg,audio/wav,audio/ogg',
                MediaType::VIDEO => $rules['files.*'][] = 'mimetypes:video/mp4,video/quicktime,video/x-msvideo',
                MediaType::DOCUMENT => $rules['files.*'][] = 'mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                MediaType::IMAGE => $rules['files.*'][] = 'mimetypes:image/jpeg,image/png,image/gif,image/webp,image/svg+xml,image/heic,image/heif',
            };
        } elseif ($this->allowedTypes !== []) {
            $mimeTypes = [
                MediaType::IMAGE->value => 'image/jpeg,image/png,image/gif,image/webp,image/svg+xml,image/heic,image/heif',
                MediaType::VIDEO->value => 'video/mp4,video/quicktime,video/x-msvideo',
                MediaType::AUDIO->value => 'audio/mpeg,audio/wav,audio/ogg',
                MediaType::DOCUMENT->value => 'application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ];

            $allowed = collect($this->allowedTypes)
                ->map(fn (string $value): ?string => $mimeTypes[$value] ?? null)
                ->filter()
                ->implode(',');

            if ($allowed !== '') {
                $rules['files.*'][] = 'mimetypes:'.$allowed;
            }
        }

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
            $originalExtension = $file->getClientOriginalExtension();
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $isSvg = mb_strtolower($originalExtension) === 'svg';
            $isHeic = in_array(mb_strtolower($originalExtension), ['heic', 'heif'], true)
                || str_contains(mb_strtolower((string) $file->getMimeType()), 'hei');
            $extension = $isHeic ? 'jpg' : $originalExtension;
            $filename = $uuid.'_'.Str::slug($originalName).'.'.$extension;
            $path = 'media';
            $width = $metadata['width'] ?? null;
            $height = $metadata['height'] ?? null;

            if ($isSvg) {
                $clean = (new Sanitizer)->sanitize((string) file_get_contents($file->getRealPath()));

                Storage::disk(config('filesystems.media'))
                    ->put("$path/$filename", $clean === false ? '' : $clean, 'public');

                $size = $clean === false ? 0 : mb_strlen($clean, '8bit');
                $mimeType = 'image/svg+xml';
            } elseif ($isHeic) {
                try {
                    $imagick = new Imagick($file->getRealPath());

                    match ($imagick->getImageOrientation()) {
                        Imagick::ORIENTATION_BOTTOMRIGHT => $imagick->rotateImage('#000', 180),
                        Imagick::ORIENTATION_RIGHTTOP => $imagick->rotateImage('#000', 90),
                        Imagick::ORIENTATION_LEFTBOTTOM => $imagick->rotateImage('#000', -90),
                        default => null,
                    };
                    $imagick->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);

                    $imagick->setImageFormat('jpeg');
                    $imagick->setImageCompressionQuality(85);

                    $jpeg = $imagick->getImageBlob();
                    $width = $imagick->getImageWidth();
                    $height = $imagick->getImageHeight();

                    $imagick->clear();
                    $imagick->destroy();

                    Storage::disk(config('filesystems.media'))
                        ->put("$path/$filename", $jpeg, 'public');

                    $size = mb_strlen($jpeg, '8bit');
                    $mimeType = 'image/jpeg';
                } catch (\Throwable) {
                    Flux::toast(
                        variant: 'danger',
                        heading: __('Upload Failed'),
                        text: __('":filename" couldn\'t be converted. Please upload it as a JPG or PNG instead.', [
                            'filename' => $file->getClientOriginalName(),
                        ]),
                        duration: 10000,
                    );
                    $file->delete();

                    continue;
                }
            } else {
                $file->storeAs($path, $filename, [
                    'disk' => config('filesystems.media'),
                    'visibility' => 'public',
                ]);

                $size = $file->getSize();
                $mimeType = $file->getMimeType();
            }

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
                'type' => MediaType::fromMimeType($mimeType)->value,
                'source' => "$path/$filename",
                'etag' => $etag,
                'filename' => $originalName.'.'.$extension,
                'alt_text' => $originalName,
                'mime_type' => $mimeType,
                'thumbnail' => $thumbnail,
                'size' => $size,
                'duration' => $metadata['duration'] ?? null,
                'width' => $width,
                'height' => $height,
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
            'source' => $media->source,
            'preview' => $media->preview,
            'crop_src' => $media->cropSrc,
            'filename' => $media->filename,
            'alt_text' => $media->alt_text,
            'mime_type' => $media->mime_type,
            'thumbnail' => $media->thumbnail,
            'icon' => $media->type->icon(),
            'size' => $media->size,
            'duration' => $media->duration,
            'width' => $media->width,
            'height' => $media->height,
            'dimensions' => $media->dimensions,
            'created_at' => $media->created_at->toDateTimeString(),
        ];
    }
};
?>

<div>
    <flux:modal wire:model.self="showLibrary" class="w-full max-w-7xl outline-0!" :closable="false" :dismissible="false">
        <div class="grid gap-6 md:grid-cols-7">
            <div class="md:col-span-5">
                <div class="flex flex-col md:flex-row gap-4 md:gap-6 justify-between md:items-center mb-6">
                    <flux:heading class="text-lg!">{{ $pexelsMode ? __('Search Pexels') : __('Media Library') }}</flux:heading>
                    <div class="flex items-center gap-3">
                        @if($pexelsMode)
                            @if($this->pexelsHasBothKinds())
                            <div class="w-full md:w-40 sm:shrink-0">
                                <flux:select variant="listbox" wire:model.live="pexelsKind">
                                    <flux:select.option value="image">{{ __('Photos') }}</flux:select.option>
                                    <flux:select.option value="video">{{ __('Videos') }}</flux:select.option>
                                </flux:select>
                            </div>
                            @endif
                            <div class="w-full md:w-52 sm:shrink-0">
                                <flux:input icon="magnifying-glass" wire:model.live.debounce.500ms="pexelsQuery" placeholder="{{ __('Search Pexels...') }}" clearable />
                            </div>
                        @else
                            <div class="w-full md:w-52 sm:shrink-0">
                                <flux:select variant="listbox" wire:model.live="typeFilter" :disabled="$type !== null" placeholder="{{ __('Filter by type') }}">
                                    <flux:select.option value="">{{ __('All Media') }}</flux:select.option>
                                    @foreach(($allowedTypes !== [] ? array_map([MediaType::class, 'from'], $allowedTypes) : MediaType::cases()) as $typeOption)
                                    <flux:select.option value="{{ $typeOption->value }}">{{ $typeOption->label(true) }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </div>
                            <div class="w-full md:w-52 sm:shrink-0">
                                <flux:input icon="magnifying-glass" wire:model.live="search" placeholder="{{ __('Search...') }}" clearable />
                            </div>
                        @endif

                        @if($this->pexelsEnabled() && $this->pexelsSupported())
                            @if($pexelsMode)
                            <flux:button wire:click="togglePexels" square variant="filled" icon="x-mark" :tooltip="__('Back to library')" aria-label="{{ __('Back to library') }}" />
                            @else
                            <flux:button wire:click="togglePexels" square variant="filled" :tooltip="__('Search in Pexels')" aria-label="{{ __('Search in Pexels') }}">
                                <svg viewBox="0 0 48 48" class="size-4.5" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <path fill="#05A081" d="M8 0h32a8 8 0 0 1 8 8v32a8 8 0 0 1-8 8H8a8 8 0 0 1-8-8V8a8 8 0 0 1 8-8Z"/>
                                    <path fill="#fff" d="M18 13h9a8.5 8.5 0 0 1 0 17h-4.5v5H18V13Zm4.5 4v9H27a4.5 4.5 0 1 0 0-9h-4.5Z"/>
                                </svg>
                            </flux:button>
                            @endif
                        @endif
                    </div>
                </div>
                
                <div @class(['hidden' => $pexelsMode])>
                @php($acceptsVideo = $type === MediaType::VIDEO || in_array(MediaType::VIDEO->value, $allowedTypes, true))
                @php($imageLimit = Number::fileSize($this->maxImageKilobytes() * 1024, precision: 0))
                @php($videoLimit = Number::fileSize($this->maxVideoKilobytes() * 1024, precision: 0))
                <flux:file-upload multiple>
                    <flux:file-upload.dropzone
                        heading="{{ __('Drop files or click to browse') }}"
                        text="{{ $acceptsVideo ? __('Images up to :image, videos up to :video', ['image' => $imageLimit, 'video' => $videoLimit]) : __('JPG, PNG, GIF up to :image', ['image' => $imageLimit]) }}"
                        with-progress
                        inline
                        class="justify-center"
                    />
                </flux:file-upload>

                <div 
                    x-data="mediaLibrary($wire)"
                    class="mt-6 grid content-start min-h-100 overflow-y-auto overscroll-contain p-2 select-none md:h-[calc(100vh-22rem)] grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4" wire:loading.class="opacity-60 animate-pulse" wire:target="loadMore">
                    
                    @if(!$loaded)
                        @for($i = 0; $i < 10; $i++)
                        <flux:skeleton animate="shimmer" class="size-full aspect-square rounded-sm" wire:loading />
                        @endfor
                    @endif

                    @forelse ($medias as $media)
                    <div class="relative cursor-pointer"
                        wire:key="media-{{ $media['id'] }}"
                        data-media-id="{{ $media['id'] }}"
                        data-index="{{ $loop->index }}"
                        @click="select({{ $media['id'] }}, {{ $loop->index }}, $event)"
                        @mouseenter="hoverIndex = {{ $loop->index }}"
                        @if($this->isSelected($media['id'])) data-selected @endif
                    >
                        <img 
                            src="{{ $media['preview'] }}" alt="{{ $media['alt_text'] }}"
                            class="w-full aspect-square object-contain bg-black/10 dark:bg-white/5 border border-zinc-200 dark:border-white/20 rounded-sm data-selected:outline-3 data-selected:outline-offset-3 data-selected:outline-sky-500 dark:data-selected:outline-sky-600 data-selected:opacity-60 data-selected:scale-95"
                            :class="{ 'outline-4 outline-offset-2 outline-sky-400/40 dark:outline-sky-700/50 opacity-60': isInRange({{ $loop->index }}) }"
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

                @if($pexelsMode)
                <div class="mt-6 flex flex-col md:h-[calc(100vh-22rem)]">
                    <div class="grid content-start min-h-100 grow overflow-y-auto overscroll-contain p-2 grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4"
                        wire:loading.class="opacity-60 animate-pulse" wire:target="searchPexels, loadMorePexels, updatedPexelsQuery, updatedPexelsKind">

                        @if(!$pexelsLoaded)
                            @for($i = 0; $i < 10; $i++)
                            <flux:skeleton animate="shimmer" class="size-full aspect-square rounded-sm" />
                            @endfor
                        @endif

                        @forelse ($pexelsResults as $item)
                        <div class="group relative cursor-pointer"
                            wire:key="pexels-{{ $item['type'] }}-{{ $item['id'] }}"
                            wire:click="previewPexels({{ $item['id'] }})"
                        >
                            <img
                                src="{{ $item['thumb'] }}" alt="{{ $item['alt'] }}" loading="lazy"
                                class="w-full aspect-square object-contain border border-zinc-200 dark:border-white/20 rounded-sm data-selected:outline-3 data-selected:outline-offset-3 data-selected:outline-sky-500 dark:data-selected:outline-sky-600"
                                style="background-color: {{ $item['avg_color'] ?: 'transparent' }}"
                                @if(($pexelsPreview['id'] ?? null) === $item['id']) data-selected @endif
                            />

                            @if($item['type'] === MediaType::VIDEO->value)
                            <div class="absolute top-2 right-2 flex items-center gap-1 bg-black/60 rounded-full px-2 py-1">
                                <flux:icon name="video-camera" class="size-3.5 text-white" />
                                @if($item['duration'])
                                <span class="text-[11px] leading-none text-white">{{ gmdate('i:s', (int) $item['duration']) }}</span>
                                @endif
                            </div>
                            @endif

                            <div class="absolute inset-x-0 bottom-0 flex items-end bg-gradient-to-t from-black/75 to-transparent p-2 pt-6 opacity-0 group-hover:opacity-100 transition rounded-b-sm">
                                <a href="{{ $item['photographer_url'] }}" target="_blank" rel="noopener noreferrer" @click.stop
                                    class="text-xs text-white/90 hover:text-white truncate">
                                    {{ $item['type'] === MediaType::VIDEO->value
                                        ? __('Video by :name on Pexels', ['name' => $item['photographer']])
                                        : __('Photo by :name on Pexels', ['name' => $item['photographer']]) }}
                                </a>
                            </div>
                        </div>
                        @empty

                        @if($pexelsLoaded)
                        <div class="col-span-full text-center py-10">
                            <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('No results found on Pexels.') }}</flux:text>
                        </div>
                        @endif

                        @endforelse

                        @if($pexelsHasMore)
                        <div wire:intersect.margin.100px="loadMorePexels"></div>
                        @endif
                    </div>

                    <a href="https://www.pexels.com" target="_blank" rel="noopener noreferrer"
                        class="mt-3 inline-flex items-center gap-1.5 self-start text-xs text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200">
                        {{ __('Photos provided by') }} <span class="font-semibold text-[#05A081]">Pexels</span>
                    </a>
                </div>
                @endif
            </div>

            <div class="md:col-span-2 md:border-l md:border-zinc-200 md:dark:border-zinc-700 md:pl-6 flex flex-col gap-4 h-full relative">
                @if($pexelsMode)
                    @if($pexelsPreview)
                    <div class="w-full bg-zinc-100 dark:bg-zinc-700 border border-zinc-300 dark:border-zinc-800 rounded-sm overflow-hidden">
                        @if($pexelsPreview['type'] === MediaType::VIDEO->value)
                        <video src="{{ $pexelsPreview['download_url'] }}" poster="{{ $pexelsPreview['preview'] }}" class="w-full aspect-video object-contain bg-black" controls preload="none" playsinline></video>
                        @else
                        <img src="{{ $pexelsPreview['preview'] }}" alt="{{ $pexelsPreview['alt'] }}" class="w-full aspect-video object-contain" style="background-color: {{ $pexelsPreview['avg_color'] ?: 'transparent' }}" />
                        @endif
                    </div>

                    <div class="grow mt-4 space-y-6">
                        <div>
                            <flux:heading size="lg" class="truncate">{{ $pexelsPreview['alt'] !== '' ? $pexelsPreview['alt'] : __('Photo by :name', ['name' => $pexelsPreview['photographer']]) }}</flux:heading>
                            <flux:text>{{ $pexelsPreview['type'] === MediaType::VIDEO->value ? __('Video') : __('Photo') }}</flux:text>
                        </div>

                        <div>
                            <flux:heading>{{ __('Information') }}</flux:heading>
                            @if($pexelsPreview['width'] && $pexelsPreview['height'])
                            <div class="my-2 h-px bg-zinc-200 dark:bg-zinc-700"></div>
                            <div class="flex items-center justify-between">
                                <flux:text class="text-xs">{{ __('Dimensions') }}</flux:text>
                                <flux:text class="text-xs">{{ $pexelsPreview['width'] }} x {{ $pexelsPreview['height'] }}</flux:text>
                            </div>
                            @endif
                            @if($pexelsPreview['duration'])
                            <div class="my-2 h-px bg-zinc-200 dark:bg-zinc-700"></div>
                            <div class="flex items-center justify-between">
                                <flux:text class="text-xs">{{ __('Duration') }}</flux:text>
                                <flux:text class="text-xs">{{ gmdate('H:i:s', (int) $pexelsPreview['duration']) }}</flux:text>
                            </div>
                            @endif
                            <div class="my-2 h-px bg-zinc-200 dark:bg-zinc-700"></div>
                            <div class="flex items-center justify-between gap-2">
                                <flux:text class="text-xs shrink-0">{{ __('Credit') }}</flux:text>
                                <a href="{{ $pexelsPreview['photographer_url'] }}" target="_blank" rel="noopener noreferrer" class="text-xs truncate underline hover:no-underline text-zinc-500 dark:text-zinc-400">
                                    {{ $pexelsPreview['type'] === MediaType::VIDEO->value
                                        ? __('Video by :name on Pexels', ['name' => $pexelsPreview['photographer']])
                                        : __('Photo by :name on Pexels', ['name' => $pexelsPreview['photographer']]) }}
                                </a>
                            </div>
                            <div class="my-2 h-px bg-zinc-200 dark:bg-zinc-700"></div>
                            <div class="flex items-center justify-between gap-2">
                                <flux:text class="text-xs shrink-0">{{ __('Source') }}</flux:text>
                                <a href="{{ $pexelsPreview['pexels_url'] }}" target="_blank" rel="noopener noreferrer" class="text-xs truncate underline hover:no-underline text-zinc-500 dark:text-zinc-400">{{ __('View on Pexels') }}</a>
                            </div>
                        </div>
                    </div>
                    @else
                    <div class="grow flex items-center justify-center text-center">
                        <flux:text size="sm" class="text-zinc-500 dark:text-zinc-400">{{ __('Select a Pexels result to preview it.') }}</flux:text>
                    </div>
                    @endif

                    <div class="flex justify-between items-center gap-4 mt-6">
                        <flux:button wire:click="togglePexels" class="w-full">{{ __('Back') }}</flux:button>
                        @if($pexelsPreview)
                        <flux:button variant="primary" wire:click="importFromPexels({{ $pexelsPreview['id'] }})" class="w-full">{{ __('Import') }}</flux:button>
                        @endif
                    </div>
                @else
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
                        @if($loop->first && $media->type === MediaType::VIDEO)
                        <video src="{{ Storage::disk(config('filesystems.media'))->url($media->source) }}" poster="{{ $media->preview }}" class="w-full aspect-video object-contain bg-black" controls autoplay muted loop playsinline></video>
                        @elseif($loop->first && $media->type === MediaType::AUDIO)
                        <div class="relative w-full aspect-video" x-data="{ playing: false }">
                            <img src="{{ $media->preview }}" alt="{{ $media->alt_text }}" class="w-full aspect-video object-contain" />
                            <audio x-ref="audio" src="{{ Storage::disk(config('filesystems.media'))->url($media->source) }}" preload="none" x-on:ended="playing = false"></audio>
                            <button type="button" class="absolute inset-0 flex items-center justify-center" aria-label="{{ __('Play preview') }}" x-on:click="playing ? $refs.audio.pause() : $refs.audio.play(); playing = ! playing">
                                <span class="flex size-14 items-center justify-center rounded-full bg-black/55 text-white transition hover:bg-black/70">
                                    <flux:icon name="play" x-show="!playing" class="size-7" />
                                    <flux:icon name="pause" x-show="playing" x-cloak class="size-7" />
                                </span>
                            </button>
                            @if($media->filename)
                            <div class="absolute bottom-2 left-1/2 -translate-x-1/2 text-center pointer-events-none px-2 w-full">
                                <flux:text class="text-xs break-all" variant="subtle">{{ $media->filename }}</flux:text>
                            </div>
                            @endif
                        </div>
                        @else
                        <img src="{{ $media->preview }}" alt="{{ $media->alt_text }}" class="w-full aspect-video object-contain" />
                        @if($media->type->icon() !== 'photo' && !$media->thumbnail)
                        <div class="absolute top-1/2 -translate-y-1/2 left-1/2 -translate-x-1/2 text-center pointer-events-none px-2 w-full">
                            <flux:text class="text-sm break-all" variant="subtle">{{ $media->filename }}</flux:text>
                        </div>
                        @endif
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
                                <flux:button icon="pencil" square tooltip="{{ __('Edit') }}" wire:click="edit"></flux:button>
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
                                @if(($firstMedia->metadata['source'] ?? null) === 'pexels')
                                <div class="my-2 h-px bg-zinc-200 dark:bg-zinc-700"></div>
                                <div class="flex items-center justify-between gap-2">
                                    <flux:text class="text-xs shrink-0">{{ __('Credit') }}</flux:text>
                                    <a href="{{ $firstMedia->metadata['photographer_url'] ?? 'https://www.pexels.com' }}" target="_blank" rel="noopener noreferrer" class="text-xs truncate underline hover:no-underline text-zinc-500 dark:text-zinc-400">
                                        {{ __('Photo by :name on Pexels', ['name' => $firstMedia->metadata['photographer'] ?? '']) }}
                                    </a>
                                </div>
                                @endif
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
                        {{ $target === 'media-gallery' ? __('Close') : __('Cancel') }}
                    </flux:button>
                    @if($target !== 'media-gallery' && $selected->count() > 0)
                    <flux:button variant="primary" wire:click="insertMedia" class="w-full">
                        {{ __('Insert') }}
                    </flux:button>
                    @endif
                </div>

                @error('files')
                    <div x-data x-init="$flux.toast({ variant: 'danger', heading: '{{ __('Upload Failed') }}', text: '{{ __('Try a smaller file or different format.') }}', duration: 10000 })"></div>
                @enderror
                @endif
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model.self="showEditModal" class="min-w-88">
        <form wire:submit="update" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Edit file') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Modifying this item will update its content and details everywhere it’s used.') }}</flux:text>
            </div>
            <flux:input label="{{ __('Name') }}" wire:model="altText" autofocus />

            {{-- <div x-show="$wire.selected?.type === 'video' && $wire.selected?.encoded?.status == 'COMPLETE'" x-cloak>
                <flux:label>Preview image</flux:label>
                <div class="grid grid-cols-4 gap-3 mt-3">
                    <template x-for="(preview, index) in $wire.selected?.encoded?.previews" :key="preview.url">
                        <button type="button" class="aspect-video bg-black"
                            :class="{ 'outline-5 outline-green-400': preview.id === $wire.form.previewId }"
                            x-on:click="switchPreview(preview)">
                            <img :src="preview.url" :alt="`Preview ${index}`" class="w-full" />
                        </button>
                    </template>
                </div>
            </div> --}}

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showDeleteModal" class="min-w-88">
        @php($usages = $showDeleteModal ? $this->selectedUsages() : [])
        @php($blocked = collect($usages)->filter(fn (array $usage): bool => $usage['labels'] !== []))
        @php($deletableCount = count($usages) - $blocked->count())
        <div class="space-y-6">
            @if($blocked->isNotEmpty())
                <div>
                    <flux:heading size="lg">{{ $blocked->count() > 1 ? __('Files in use') : __('File in use') }}</flux:heading>
                    <flux:text class="mt-2">
                        {{ $blocked->count() > 1
                            ? __(':count of the selected files are in use and can\'t be deleted.', ['count' => $blocked->count()])
                            : __('This file is in use and can\'t be deleted.') }}
                    </flux:text>
                </div>

                <div class="space-y-4 max-h-64 overflow-y-auto">
                    @foreach($blocked as $usage)
                    <div wire:key="usage-{{ $usage['id'] }}">
                        @if($blocked->count() > 1)
                        <flux:text class="font-medium break-all">{{ $usage['filename'] }}</flux:text>
                        @endif
                        <flux:text variant="subtle" class="text-sm">{{ __('Used in:') }}</flux:text>
                        <ul class="mt-1 ml-1 space-y-1">
                            @foreach($usage['labels'] as $label)
                            <li class="flex items-start gap-2">
                                <flux:icon name="link" class="size-3.5 mt-0.5 shrink-0 text-zinc-400" />
                                <flux:text class="text-sm">{{ $label }}</flux:text>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @endforeach
                </div>

                <flux:text variant="subtle" class="text-sm">{{ __('Remove it from those places first, then you can delete it.') }}</flux:text>

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Close') }}</flux:button>
                    </flux:modal.close>
                    @if($deletableCount > 0)
                    <flux:button variant="danger" wire:click="deleteCurrentItem">{{ __('Delete the other :count', ['count' => $deletableCount]) }}</flux:button>
                    @endif
                </div>
            @else
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
            @endif
        </div>
    </flux:modal>
</div>

<script>
    this.el.querySelector('input[type="file"]')?.addEventListener('change', async (e) => {
        await uploadFiles(e.target.files);
    });

    this.el.querySelector('[data-flux-file-upload]')?.addEventListener('drop', async (e) => {
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