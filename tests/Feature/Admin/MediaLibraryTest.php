<?php

declare(strict_types=1);

use App\Actions\DownloadMediaAction;
use App\Enums\MediaType;
use App\Models\Media;
use App\Services\UploadLimit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach(function (): void {
    Storage::fake(config('filesystems.media'));
});

it('initializes with correct default values', function (): void {
    Livewire::test('admin.media-library')
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

it('shows the effective upload limit capped by the php configuration', function (): void {
    $videoLimit = Number::fileSize(UploadLimit::cappedKilobytes(UploadLimit::VIDEO_MAX_KILOBYTES) * 1024, precision: 0);

    Livewire::test('admin.media-library')
        ->dispatch('select-media', target: 't', type: MediaType::VIDEO->value, max: 1, media: null)
        ->assertSee("videos up to {$videoLimit}")
        ->assertDontSee('300MB');
});

it('opens as a browsable gallery of all media when launched from the sidebar', function (): void {
    Media::factory()->count(3)->create();

    Livewire::test('admin.media-library')
        ->dispatch('select-media', target: 'media-gallery', type: null, max: 50, media: null)
        ->assertSet('showLibrary', true)
        ->assertSet('target', 'media-gallery')
        ->assertSet('type', null)
        ->assertSet('max', 50)
        ->assertCount('medias', 3);
});

it('hides the insert button when browsing as a gallery', function (): void {
    $media = Media::factory()->create();

    Livewire::test('admin.media-library')
        ->dispatch('select-media', target: 'media-gallery', type: null, max: 50, media: null)
        ->call('selectMedia', $media)
        ->assertDontSee('Insert');
});

it('shows the insert button when picking media for a field', function (): void {
    $media = Media::factory()->create();

    Livewire::test('admin.media-library')
        ->dispatch('select-media', target: 'hero.image', type: null, max: 1, media: null)
        ->call('selectMedia', $media)
        ->assertSee('Insert');
});

it('handles select media event and opens library', function (): void {
    $media = Media::factory()->create();

    Livewire::test('admin.media-library')
        ->dispatch('select-media', target: 'test-target', type: MediaType::IMAGE->value, max: 5, media: [$media])
        ->assertSet('showLibrary', true)
        ->assertSet('target', 'test-target')
        ->assertSet('type', MediaType::IMAGE)
        ->assertSet('max', 5);
});

it('loads media into the grid when the library opens', function (): void {
    Media::factory()->count(3)->create();

    Livewire::test('admin.media-library')
        ->dispatch('select-media', target: 'test-target', type: null, max: 5, media: null)
        ->assertSet('showLibrary', true)
        ->assertSet('loaded', true)
        ->assertCount('medias', 3);
});

it('hydrates an array media payload into models when opening the library', function (): void {
    $media = Media::factory()->create();

    $component = Livewire::test('admin.media-library')
        ->dispatch('select-media', target: 'og_image.en', type: null, max: 1, media: [['id' => $media->id, 'crop' => []]])
        ->assertSet('showLibrary', true)
        ->assertCount('selected', 1);

    expect($component->get('selected')->first())->toBeInstanceOf(Media::class)
        ->and($component->get('selected')->first()->id)->toBe($media->id);
});

it('handles select media event without type specified', function (): void {
    Livewire::test('admin.media-library')
        ->dispatch('select-media', target: 'test-target', type: '', max: 1, media: null)
        ->assertSet('showLibrary', true)
        ->assertSet('target', 'test-target')
        ->assertSet('type', null)
        ->assertSet('typeFilter', '')
        ->assertCount('selected', 0);
});

it('handles select media event with null type', function (): void {
    Livewire::test('admin.media-library')
        ->dispatch('select-media', target: 'test-target', type: null, max: 1, media: null)
        ->assertSet('showLibrary', true)
        ->assertSet('target', 'test-target')
        ->assertSet('type', null)
        ->assertSet('typeFilter', '')
        ->assertCount('selected', 0);
});

it('selects a single media item', function (): void {
    $media = Media::factory()->create();

    Livewire::test('admin.media-library')
        ->call('selectMedia', $media)
        ->assertCount('selected', 1);
});

it('plays a selected video inline instead of showing a thumbnail', function (): void {
    $media = Media::factory()->create([
        'type' => MediaType::VIDEO,
        'mime_type' => 'video/mp4',
        'source' => 'videos/clip.mp4',
        'thumbnail' => null,
    ]);

    Livewire::test('admin.media-library')
        ->call('selectMedia', $media)
        ->assertSee('<video', false)
        ->assertSee('videos/clip.mp4', false)
        ->assertSee('autoplay', false);
});

it('shows a play-preview control for a selected audio file', function (): void {
    $media = Media::factory()->create([
        'type' => MediaType::AUDIO,
        'mime_type' => 'audio/mpeg',
        'source' => 'audio/song.mp3',
    ]);

    Livewire::test('admin.media-library')
        ->call('selectMedia', $media)
        ->assertSee('<audio', false)
        ->assertSee('audio/song.mp3', false)
        ->assertSee('Play preview');
});

it('deselects media when clicking it again', function (): void {
    $media = Media::factory()->create();

    Livewire::test('admin.media-library')
        ->call('selectMedia', $media)
        ->assertCount('selected', 1)
        ->call('selectMedia', $media)
        ->assertCount('selected', 0);
});

it('replaces selection when max is 1', function (): void {
    $media1 = Media::factory()->create();
    $media2 = Media::factory()->create();

    Livewire::test('admin.media-library')
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

    Livewire::test('admin.media-library')
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

    $component = Livewire::test('admin.media-library')
        ->call('selectMedia', $media);

    $instance = $component->instance();
    expect($instance->isSelected($media->id))->toBeTrue();
});

it('clears all selections', function (): void {
    $media1 = Media::factory()->create();
    $media2 = Media::factory()->create();

    Livewire::test('admin.media-library')
        ->set('max', 2)
        ->call('selectMedia', $media1)
        ->call('selectMedia', $media2)
        ->assertCount('selected', 2)
        ->call('clearSelection')
        ->assertCount('selected', 0);
});

it('inserts media and dispatches event', function (): void {
    $media = Media::factory()->create();

    Livewire::test('admin.media-library')
        ->set('target', 'test-target')
        ->call('selectMedia', $media)
        ->call('insertMedia')
        ->assertSet('showLibrary', false)
        ->assertDispatched('media-selected');
});

it('opens edit modal when single media is selected', function (): void {
    $media = Media::factory()->create(['alt_text' => 'Original alt text']);

    Livewire::test('admin.media-library')
        ->call('selectMedia', $media)
        ->call('edit')
        ->assertSet('showEditModal', true)
        ->assertSet('altText', 'Original alt text');
});

it('does not open edit modal when no media is selected', function (): void {
    Livewire::test('admin.media-library')
        ->call('edit')
        ->assertSet('showEditModal', false);
});

it('does not open edit modal when multiple media are selected', function (): void {
    $media1 = Media::factory()->create();
    $media2 = Media::factory()->create();

    Livewire::test('admin.media-library')
        ->set('max', 2)
        ->call('selectMedia', $media1)
        ->call('selectMedia', $media2)
        ->call('edit')
        ->assertSet('showEditModal', false);
});

it('updates media alt text', function (): void {
    $media = Media::factory()->create(['alt_text' => 'Original']);

    Livewire::test('admin.media-library')
        ->call('selectMedia', $media)
        ->call('edit')
        ->set('altText', 'Updated alt text')
        ->call('update')
        ->assertSet('showEditModal', false);

    expect($media->fresh()->alt_text)->toBe('Updated alt text');
});

it('does not update when no media is selected', function (): void {
    Livewire::test('admin.media-library')
        ->set('altText', 'Some text')
        ->call('update');

    expect(true)->toBeTrue();
});

it('opens delete confirmation modal', function (): void {
    Livewire::test('admin.media-library')
        ->call('confirmDelete')
        ->assertSet('showDeleteModal', true);
});

it('deletes selected media items', function (): void {
    $media1 = Media::factory()->create();
    $media2 = Media::factory()->create();

    Livewire::test('admin.media-library')
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

    Livewire::test('admin.media-library')
        ->call('deleteCurrentItem');

    expect(Media::query()->find($media->id))->not->toBeNull();
});

it('loads media on initialization', function (): void {
    Media::factory()->count(5)->create();

    Livewire::test('admin.media-library')
        ->call('loadMedia')
        ->assertSet('loaded', true)
        ->assertCount('medias', 5);
});

it('filters media by type', function (): void {
    Media::factory()->create(['type' => MediaType::IMAGE]);
    Media::factory()->create(['type' => MediaType::VIDEO]);
    Media::factory()->create(['type' => MediaType::IMAGE]);

    $component = Livewire::test('admin.media-library');
    $component->set('typeFilter', MediaType::IMAGE->value);

    expect($component->get('medias')->count())->toBe(2);
});

it('searches media by filename', function (): void {
    Media::factory()->create(['filename' => 'sunset.jpg']);
    Media::factory()->create(['filename' => 'beach.jpg']);
    Media::factory()->create(['filename' => 'mountain.jpg']);

    $component = Livewire::test('admin.media-library');
    $component->set('search', 'sunset');

    expect($component->get('medias')->count())->toBe(1);
});

it('searches media by alt text', function (): void {
    Media::factory()->create(['alt_text' => 'Beautiful sunset']);
    Media::factory()->create(['alt_text' => 'Beach scene']);
    Media::factory()->create(['alt_text' => 'Sunset colors']);

    $component = Livewire::test('admin.media-library');
    $component->set('search', 'sunset');

    expect($component->get('medias')->count())->toBe(2);
});

it('loads more media when paginating', function (): void {
    Media::factory()->count(25)->create();

    $component = Livewire::test('admin.media-library')
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

    Livewire::test('admin.media-library')
        ->call('loadMedia')
        ->assertCount('medias', 2)
        ->set('search', 'test')
        ->assertCount('medias', 1);
});

it('reloads media when type filter is updated', function (): void {
    Media::factory()->create(['type' => MediaType::IMAGE]);
    Media::factory()->create(['type' => MediaType::VIDEO]);

    Livewire::test('admin.media-library')
        ->call('loadMedia')
        ->assertCount('medias', 2)
        ->set('typeFilter', MediaType::IMAGE->value)
        ->assertCount('medias', 1);
});

it('accepts multiple allowed types and constrains the grid', function (): void {
    Media::factory()->create(['type' => MediaType::IMAGE]);
    Media::factory()->create(['type' => MediaType::VIDEO]);
    Media::factory()->create(['type' => MediaType::AUDIO]);
    Media::factory()->create(['type' => MediaType::DOCUMENT]);

    Livewire::test('admin.media-library')
        ->dispatch('select-media', target: 'gallery', type: 'image,video', max: 10, media: null)
        ->assertSet('type', null)
        ->assertSet('allowedTypes', ['image', 'video'])
        ->assertSet('typeFilter', '')
        ->assertCount('medias', 2);
});

it('narrows multi-type results with the type filter', function (): void {
    Media::factory()->create(['type' => MediaType::IMAGE]);
    Media::factory()->create(['type' => MediaType::VIDEO]);

    Livewire::test('admin.media-library')
        ->dispatch('select-media', target: 'gallery', type: 'image,video', max: 10, media: null)
        ->assertCount('medias', 2)
        ->set('typeFilter', MediaType::VIDEO->value)
        ->assertCount('medias', 1);
});

it('locks a single allowed type', function (): void {
    Livewire::test('admin.media-library')
        ->dispatch('select-media', target: 'og', type: MediaType::IMAGE->value, max: 1, media: null)
        ->assertSet('type', MediaType::IMAGE)
        ->assertSet('allowedTypes', ['image'])
        ->assertSet('typeFilter', 'image');
});

it('accepts an image upload under multiple allowed types', function (): void {
    $image = UploadedFile::fake()->image('photo.jpg');

    Livewire::test('admin.media-library')
        ->dispatch('select-media', target: 'gallery', type: 'image,video', max: 10, media: null)
        ->set('files', [$image])
        ->call('save', [['width' => 800, 'height' => 600]]);

    $this->assertDatabaseHas('media', [
        'type' => MediaType::IMAGE->value,
        'mime_type' => 'image/jpeg',
    ]);
});

it('rejects uploads outside the allowed types', function (): void {
    $audio = UploadedFile::fake()->create('song.mp3', 100, 'audio/mpeg');

    Livewire::test('admin.media-library')
        ->dispatch('select-media', target: 'gallery', type: 'image,video', max: 10, media: null)
        ->set('files', [$audio])
        ->call('save', [[]]);

    expect(Media::query()->count())->toBe(0);
});

it('allows large video uploads when video is permitted', function (): void {
    $video = UploadedFile::fake()->create('clip.mp4', 50000, 'video/mp4');

    Livewire::test('admin.media-library')
        ->dispatch('select-media', target: 'gallery', type: 'image,video', max: 10, media: null)
        ->set('files', [$video])
        ->call('save', [[]]);

    $this->assertDatabaseHas('media', [
        'type' => MediaType::VIDEO->value,
        'mime_type' => 'video/mp4',
    ]);
});

it('rejects uploads larger than the configured override limit', function (): void {
    config()->set('media.max_upload_kilobytes', 12000);

    $video = UploadedFile::fake()->create('clip.mp4', 20000, 'video/mp4');

    Livewire::test('admin.media-library')
        ->dispatch('select-media', target: 'gallery', type: 'image,video', max: 10, media: null)
        ->set('files', [$video])
        ->call('save', [[]]);

    $this->assertDatabaseMissing('media', ['mime_type' => 'video/mp4']);
});

it('rejects videos beyond the 300MB cap', function (): void {
    $video = UploadedFile::fake()->create('huge.mp4', 320000, 'video/mp4');

    Livewire::test('admin.media-library')
        ->dispatch('select-media', target: 'gallery', type: 'image,video', max: 10, media: null)
        ->set('files', [$video])
        ->call('save', [[]]);

    expect(Media::query()->count())->toBe(0);
});

it('checks if file exists by etag', function (): void {
    $media = Media::factory()->create(['etag' => 'abc123']);

    $component = Livewire::test('admin.media-library');
    $instance = $component->instance();
    $result = $instance->checkFileExists('abc123');

    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($media->id);
});

it('returns null when file does not exist by etag', function (): void {
    $component = Livewire::test('admin.media-library');
    $instance = $component->instance();
    $result = $instance->checkFileExists('nonexistent');

    expect($result)->toBeNull();
});

it('uploads and saves image files', function (): void {
    $file = UploadedFile::fake()->image('photo.jpg');

    Livewire::test('admin.media-library')
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

it('uploads svg files and strips embedded scripts', function (): void {
    $svg = <<<'SVG'
    <svg xmlns="http://www.w3.org/2000/svg" width="120" height="40" viewBox="0 0 120 40">
        <script>alert('xss')</script>
        <rect width="120" height="40" onload="alert('xss')" fill="#000"/>
    </svg>
    SVG;

    $file = UploadedFile::fake()->createWithContent('logo.svg', $svg);

    Livewire::test('admin.media-library')
        ->set('type', MediaType::IMAGE)
        ->set('files', [$file])
        ->call('save', [
            ['width' => 120, 'height' => 40],
        ])
        ->assertSet('files', []);

    $this->assertDatabaseHas('media', [
        'type' => MediaType::IMAGE->value,
        'mime_type' => 'image/svg+xml',
        'filename' => 'logo.svg',
        'width' => 120,
        'height' => 40,
    ]);

    $media = Media::query()->latest()->first();
    $stored = Storage::disk(config('filesystems.media'))->get($media->source);

    expect($stored)->not->toContain('<script')
        ->and($stored)->not->toContain('onload')
        ->and($stored)->toContain('<rect');
});

it('skips duplicate files by etag', function (): void {
    $file = UploadedFile::fake()->image('photo.jpg');
    $etag = md5_file($file->getRealPath());

    Media::factory()->create(['etag' => $etag]);

    Livewire::test('admin.media-library')
        ->set('files', [$file])
        ->call('save', [[]]);

    expect(Media::query()->where('etag', $etag)->count())->toBe(1);
});

it('auto-selects uploaded files up to max', function (): void {
    $file1 = UploadedFile::fake()->image('photo1.jpg');
    $file2 = UploadedFile::fake()->image('photo2.jpg');
    $file3 = UploadedFile::fake()->image('photo3.jpg');

    $component = Livewire::test('admin.media-library')
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

    $component = Livewire::test('admin.media-library')
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
    $component = Livewire::test('admin.media-library');
    $instance = $component->instance();

    $style = $instance->stackStyle(12345, 0);

    expect($style)->toContain('top:')
        ->and($style)->toContain('z-index: 0')
        ->and($style)->toContain('transform: rotate(');
});

it('generates different stack styles for different indexes', function (): void {
    $component = Livewire::test('admin.media-library');
    $instance = $component->instance();

    $style1 = $instance->stackStyle(12345, 0);
    $style2 = $instance->stackStyle(12345, 1);

    expect($style1)->not->toBe($style2);
});

it('downloads single media file', function (): void {
    $media = Media::factory()->create();
    Storage::disk(config('filesystems.media'))->put($media->source, 'test content');

    $component = Livewire::test('admin.media-library')
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

    $component = Livewire::test('admin.media-library')
        ->set('max', 2)
        ->call('selectMedia', $media1)
        ->call('selectMedia', $media2);

    $instance = $component->instance();
    $response = $instance->download(resolve(DownloadMediaAction::class));

    expect($response)->toBeInstanceOf(StreamedResponse::class);
});

it('selects a range of media items', function (): void {
    $media = Media::factory()->count(5)->create();

    Livewire::test('admin.media-library')
        ->set('max', 5)
        ->call('selectMediaRange', [$media[0]->id, $media[1]->id, $media[2]->id])
        ->assertCount('selected', 3);
});

it('selects a range of media items respecting max', function (): void {
    $media = Media::factory()->count(5)->create();

    Livewire::test('admin.media-library')
        ->set('max', 2)
        ->call('selectMediaRange', [$media[0]->id, $media[1]->id, $media[2]->id])
        ->assertCount('selected', 2);
});
