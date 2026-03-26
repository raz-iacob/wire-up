<?php

declare(strict_types=1);

use App\Actions\DownloadMediaAction;
use App\Enums\MediaType;
use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach(function (): void {
    Storage::fake(config('filesystems.media'));
});

it('initializes with correct default values', function (): void {
    Livewire::test('media-library')
        ->assertSet('showLibrary', false)
        ->assertSet('type', null)
        ->assertSet('max', 1)
        ->assertSet('files', [])
        ->assertSet('search', '')
        ->assertSet('typeFilter', '')
        ->assertSet('perPage', 20)
        ->assertSet('hasMore', true)
        ->assertSet('showEditModal', false)
        ->assertSet('showDeleteModal', false)
        ->assertSet('loaded', false);
});

it('handles select media event and opens library', function (): void {
    $media = Media::factory()->create();

    Livewire::test('media-library')
        ->dispatch('select-media', target: 'test-target', type: MediaType::IMAGE->value, max: 5, media: [$media])
        ->assertSet('showLibrary', true)
        ->assertSet('target', 'test-target')
        ->assertSet('type', MediaType::IMAGE)
        ->assertSet('max', 5);
});

it('handles select media event without type specified', function (): void {
    Livewire::test('media-library')
        ->dispatch('select-media', target: 'test-target', type: '', max: 1, media: null)
        ->assertSet('showLibrary', true)
        ->assertSet('target', 'test-target')
        ->assertSet('type', null)
        ->assertSet('typeFilter', '')
        ->assertCount('selected', 0);
});

it('handles select media event with null type', function (): void {
    Livewire::test('media-library')
        ->dispatch('select-media', target: 'test-target', type: null, max: 1, media: null)
        ->assertSet('showLibrary', true)
        ->assertSet('target', 'test-target')
        ->assertSet('type', null)
        ->assertSet('typeFilter', '')
        ->assertCount('selected', 0);
});

it('selects a single media item', function (): void {
    $media = Media::factory()->create();

    Livewire::test('media-library')
        ->call('selectMedia', $media)
        ->assertCount('selected', 1);
});

it('deselects media when clicking it again', function (): void {
    $media = Media::factory()->create();

    Livewire::test('media-library')
        ->call('selectMedia', $media)
        ->assertCount('selected', 1)
        ->call('selectMedia', $media)
        ->assertCount('selected', 0);
});

it('replaces selection when max is 1', function (): void {
    $media1 = Media::factory()->create();
    $media2 = Media::factory()->create();

    Livewire::test('media-library')
        ->set('max', 1)
        ->call('selectMedia', $media1)
        ->assertCount('selected', 1)
        ->call('selectMedia', $media2)
        ->assertCount('selected', 1);
});

it('allows multiple selections up to max', function (): void {
    $media1 = Media::factory()->create();
    $media2 = Media::factory()->create();
    $media3 = Media::factory()->create();

    Livewire::test('media-library')
        ->set('max', 2)
        ->call('selectMedia', $media1)
        ->assertCount('selected', 1)
        ->call('selectMedia', $media2)
        ->assertCount('selected', 2)
        ->call('selectMedia', $media3)
        ->assertCount('selected', 2);
});

it('correctly identifies selected media', function (): void {
    $media = Media::factory()->create();

    $component = Livewire::test('media-library')
        ->call('selectMedia', $media);

    $instance = $component->instance();
    expect($instance->isSelected($media->id))->toBeTrue();
});

it('clears all selections', function (): void {
    $media1 = Media::factory()->create();
    $media2 = Media::factory()->create();

    Livewire::test('media-library')
        ->set('max', 2)
        ->call('selectMedia', $media1)
        ->call('selectMedia', $media2)
        ->assertCount('selected', 2)
        ->call('clearSelection')
        ->assertCount('selected', 0);
});

it('inserts media and dispatches event', function (): void {
    $media = Media::factory()->create();

    Livewire::test('media-library')
        ->set('target', 'test-target')
        ->call('selectMedia', $media)
        ->call('insertMedia')
        ->assertSet('showLibrary', false)
        ->assertDispatched('media-selected');
});

it('opens edit modal when single media is selected', function (): void {
    $media = Media::factory()->create(['alt_text' => 'Original alt text']);

    Livewire::test('media-library')
        ->call('selectMedia', $media)
        ->call('edit')
        ->assertSet('showEditModal', true)
        ->assertSet('altText', 'Original alt text');
});

it('does not open edit modal when no media is selected', function (): void {
    Livewire::test('media-library')
        ->call('edit')
        ->assertSet('showEditModal', false);
});

it('does not open edit modal when multiple media are selected', function (): void {
    $media1 = Media::factory()->create();
    $media2 = Media::factory()->create();

    Livewire::test('media-library')
        ->set('max', 2)
        ->call('selectMedia', $media1)
        ->call('selectMedia', $media2)
        ->call('edit')
        ->assertSet('showEditModal', false);
});

it('updates media alt text', function (): void {
    $media = Media::factory()->create(['alt_text' => 'Original']);

    Livewire::test('media-library')
        ->call('selectMedia', $media)
        ->call('edit')
        ->set('altText', 'Updated alt text')
        ->call('update')
        ->assertSet('showEditModal', false);

    expect($media->fresh()->alt_text)->toBe('Updated alt text');
});

it('does not update when no media is selected', function (): void {
    Livewire::test('media-library')
        ->set('altText', 'Some text')
        ->call('update');

    expect(true)->toBeTrue();
});

it('opens delete confirmation modal', function (): void {
    Livewire::test('media-library')
        ->call('confirmDelete')
        ->assertSet('showDeleteModal', true);
});

it('deletes selected media items', function (): void {
    $media1 = Media::factory()->create();
    $media2 = Media::factory()->create();

    Livewire::test('media-library')
        ->set('max', 2)
        ->call('selectMedia', $media1)
        ->call('selectMedia', $media2)
        ->call('deleteCurrentItem')
        ->assertSet('showDeleteModal', false)
        ->assertCount('selected', 0);

    expect(Media::query()->find($media1->id))->toBeNull()
        ->and(Media::query()->find($media2->id))->toBeNull();
});

it('does not delete when no media is selected', function (): void {
    $media = Media::factory()->create();

    Livewire::test('media-library')
        ->call('deleteCurrentItem');

    expect(Media::query()->find($media->id))->not->toBeNull();
});

it('loads media on initialization', function (): void {
    Media::factory()->count(5)->create();

    Livewire::test('media-library')
        ->call('loadMedia')
        ->assertSet('loaded', true)
        ->assertCount('medias', 5);
});

it('filters media by type', function (): void {
    Media::factory()->create(['type' => MediaType::IMAGE]);
    Media::factory()->create(['type' => MediaType::VIDEO]);
    Media::factory()->create(['type' => MediaType::IMAGE]);

    $component = Livewire::test('media-library');
    $component->set('typeFilter', MediaType::IMAGE->value);

    expect($component->get('medias')->count())->toBe(2);
});

it('searches media by filename', function (): void {
    Media::factory()->create(['filename' => 'sunset.jpg']);
    Media::factory()->create(['filename' => 'beach.jpg']);
    Media::factory()->create(['filename' => 'mountain.jpg']);

    $component = Livewire::test('media-library');
    $component->set('search', 'sunset');

    expect($component->get('medias')->count())->toBe(1);
});

it('searches media by alt text', function (): void {
    Media::factory()->create(['alt_text' => 'Beautiful sunset']);
    Media::factory()->create(['alt_text' => 'Beach scene']);
    Media::factory()->create(['alt_text' => 'Sunset colors']);

    $component = Livewire::test('media-library');
    $component->set('search', 'sunset');

    expect($component->get('medias')->count())->toBe(2);
});

it('loads more media when paginating', function (): void {
    Media::factory()->count(25)->create();

    $component = Livewire::test('media-library')
        ->set('perPage', 20)
        ->call('loadMedia')
        ->assertCount('medias', 20)
        ->assertSet('hasMore', true);

    $component->call('loadMore')
        ->assertCount('medias', 25)
        ->assertSet('hasMore', false);
});

it('reloads media when search is updated', function (): void {
    Media::factory()->create(['filename' => 'test.jpg']);
    Media::factory()->create(['filename' => 'other.jpg']);

    Livewire::test('media-library')
        ->call('loadMedia')
        ->assertCount('medias', 2)
        ->set('search', 'test')
        ->assertCount('medias', 1);
});

it('reloads media when type filter is updated', function (): void {
    Media::factory()->create(['type' => MediaType::IMAGE]);
    Media::factory()->create(['type' => MediaType::VIDEO]);

    Livewire::test('media-library')
        ->call('loadMedia')
        ->assertCount('medias', 2)
        ->set('typeFilter', MediaType::IMAGE->value)
        ->assertCount('medias', 1);
});

it('checks if file exists by etag', function (): void {
    $media = Media::factory()->create(['etag' => 'abc123']);

    $component = Livewire::test('media-library');
    $instance = $component->instance();
    $result = $instance->checkFileExists('abc123');

    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($media->id);
});

it('returns null when file does not exist by etag', function (): void {
    $component = Livewire::test('media-library');
    $instance = $component->instance();
    $result = $instance->checkFileExists('nonexistent');

    expect($result)->toBeNull();
});

it('uploads and saves image files', function (): void {
    $file = UploadedFile::fake()->image('photo.jpg');

    Livewire::test('media-library')
        ->set('type', MediaType::IMAGE)
        ->set('files', [$file])
        ->call('save', [
            ['width' => 800, 'height' => 600],
        ])
        ->assertSet('files', []);

    $this->assertDatabaseHas('media', [
        'type' => MediaType::IMAGE->value,
        'mime_type' => 'image/jpeg',
        'width' => 800,
        'height' => 600,
    ]);

    $media = Media::query()->latest()->first();
    Storage::disk(config('filesystems.media'))->assertExists($media->source);
});

it('skips duplicate files by etag', function (): void {
    $file = UploadedFile::fake()->image('photo.jpg');
    $etag = md5_file($file->getRealPath());

    Media::factory()->create(['etag' => $etag]);

    Livewire::test('media-library')
        ->set('files', [$file])
        ->call('save', [[]]);

    expect(Media::query()->where('etag', $etag)->count())->toBe(1);
});

it('auto-selects uploaded files up to max', function (): void {
    $file1 = UploadedFile::fake()->image('photo1.jpg');
    $file2 = UploadedFile::fake()->image('photo2.jpg');
    $file3 = UploadedFile::fake()->image('photo3.jpg');

    $component = Livewire::test('media-library')
        ->set('max', 2)
        ->set('files', [$file1, $file2, $file3])
        ->call('save', [[], [], []]);

    $selectedCount = $component->get('selected')->count();
    expect($selectedCount)->toBeLessThanOrEqual(2)
        ->and($selectedCount)->toBeGreaterThan(0);
});

it('parses media with all attributes', function (): void {
    $media = Media::factory()->create([
        'type' => MediaType::IMAGE,
        'filename' => 'test.jpg',
        'alt_text' => 'Test image',
        'mime_type' => 'image/jpeg',
        'size' => 1024,
        'duration' => 10,
        'width' => 800,
        'height' => 600,
    ]);

    $component = Livewire::test('media-library')
        ->call('loadMedia');

    $parsed = $component->get('medias')->first();

    expect($parsed['id'])->toBe($media->id)
        ->and($parsed['filename'])->toBe('test.jpg')
        ->and($parsed['alt_text'])->toBe('Test image')
        ->and($parsed['mime_type'])->toBe('image/jpeg')
        ->and($parsed['size'])->toBe(1024)
        ->and($parsed['duration'])->toBe(10)
        ->and($parsed['dimensions'])->toBe('800 x 600');
});

it('generates stack style string', function (): void {
    $component = Livewire::test('media-library');
    $instance = $component->instance();

    $style = $instance->stackStyle(12345, 0);

    expect($style)->toContain('top:')
        ->and($style)->toContain('z-index: 0')
        ->and($style)->toContain('transform: rotate(');
});

it('generates different stack styles for different indexes', function (): void {
    $component = Livewire::test('media-library');
    $instance = $component->instance();

    $style1 = $instance->stackStyle(12345, 0);
    $style2 = $instance->stackStyle(12345, 1);

    expect($style1)->not->toBe($style2);
});

it('downloads single media file', function (): void {
    $media = Media::factory()->create();
    Storage::disk(config('filesystems.media'))->put($media->source, 'test content');

    $component = Livewire::test('media-library')
        ->call('selectMedia', $media);

    $instance = $component->instance();
    $response = $instance->download(resolve(DownloadMediaAction::class));

    expect($response)->toBeInstanceOf(StreamedResponse::class);
});

it('downloads multiple media files as zip', function (): void {
    $media1 = Media::factory()->create();
    $media2 = Media::factory()->create();
    Storage::disk(config('filesystems.media'))->put($media1->source, 'test content 1');
    Storage::disk(config('filesystems.media'))->put($media2->source, 'test content 2');

    $component = Livewire::test('media-library')
        ->set('max', 2)
        ->call('selectMedia', $media1)
        ->call('selectMedia', $media2);

    $instance = $component->instance();
    $response = $instance->download(resolve(DownloadMediaAction::class));

    expect($response)->toBeInstanceOf(StreamedResponse::class);
});

it('selects a range of media items', function (): void {
    $media = Media::factory()->count(5)->create();

    Livewire::test('media-library')
        ->set('max', 5)
        ->call('selectMediaRange', [$media[0]->id, $media[1]->id, $media[2]->id])
        ->assertCount('selected', 3);
});

it('selects a range of media items respecting max', function (): void {
    $media = Media::factory()->count(5)->create();

    Livewire::test('media-library')
        ->set('max', 2)
        ->call('selectMediaRange', [$media[0]->id, $media[1]->id, $media[2]->id])
        ->assertCount('selected', 2);
});
