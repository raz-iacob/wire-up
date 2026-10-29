<?php

declare(strict_types=1);

use App\Actions\SetMediaPrivacyAction;
use App\Actions\StoreMediaFileAction;
use App\Enums\BlockType;
use App\Enums\MediaType;
use App\Mcp\Servers\WireUpServer;
use App\Mcp\Tools\UploadMediaTool;
use App\Models\Block;
use App\Models\Media;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use App\Services\ImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    Storage::fake(config()->string('filesystems.media'));
    Storage::fake(config()->string('filesystems.private_media'));

    $this->publicDisk = Storage::disk(config()->string('filesystems.media'));
    $this->privateDisk = Storage::disk(config()->string('filesystems.private_media'));
});

function privateDocument(string $source = 'media/contract.pdf', ?string $thumbnail = null): Media
{
    Storage::disk(config()->string('filesystems.private_media'))->put($source, 'signed contract');

    return Media::factory()->private()->create(['source' => $source, 'filename' => 'contract.pdf', 'thumbnail' => $thumbnail]);
}

it('stores a private document upload, and its preview, on the private disk', function (): void {
    $file = UploadedFile::fake()->create('contract.pdf', 10, 'application/pdf');

    $media = resolve(StoreMediaFileAction::class)->handle($file->getRealPath(), 'contract.pdf', [
        'mime_type' => 'application/pdf',
        'thumbnail' => 'data:image/png;base64,'.base64_encode('page one'),
    ], private: true);

    expect($media->is_private)->toBeTrue();

    $this->privateDisk->assertExists($media->source);
    $this->privateDisk->assertExists((string) $media->thumbnail);
    $this->publicDisk->assertMissing($media->source);
    $this->publicDisk->assertMissing((string) $media->thumbnail);
});

it('keeps images public even when asked to store them privately', function (): void {
    $image = UploadedFile::fake()->image('photo.jpg');

    $media = resolve(StoreMediaFileAction::class)->handle($image->getRealPath(), 'photo.jpg', ['mime_type' => 'image/jpeg'], private: true);

    expect($media->is_private)->toBeFalse();

    $this->publicDisk->assertExists($media->source);
});

it('makes an identical public document private when it is uploaded privately', function (): void {
    $file = UploadedFile::fake()->create('contract.pdf', 10, 'application/pdf');
    $action = resolve(StoreMediaFileAction::class);

    $public = $action->handle($file->getRealPath(), 'contract.pdf', ['mime_type' => 'application/pdf']);
    $again = $action->handle($file->getRealPath(), 'contract.pdf', ['mime_type' => 'application/pdf'], private: true);

    expect($again->id)->toBe($public->id)
        ->and($public->fresh()->is_private)->toBeTrue();

    $this->privateDisk->assertExists($public->source);
    $this->publicDisk->assertMissing($public->source);
});

it('moves a document and its preview between the public and private disks', function (): void {
    $this->publicDisk->put('media/contract.pdf', 'signed');
    $this->publicDisk->put('media/contract_thumb.png', 'page one');
    $media = Media::factory()->create(['type' => MediaType::DOCUMENT, 'source' => 'media/contract.pdf', 'thumbnail' => 'media/contract_thumb.png']);
    $privacy = new SetMediaPrivacyAction;

    $privacy->handle($media, true);

    expect($media->fresh()->is_private)->toBeTrue()
        ->and($this->privateDisk->get('media/contract.pdf'))->toBe('signed');

    $this->privateDisk->assertExists('media/contract_thumb.png');
    $this->publicDisk->assertMissing('media/contract.pdf');

    $privacy->handle($media->fresh(), false);

    expect($media->fresh()->is_private)->toBeFalse()
        ->and($this->publicDisk->get('media/contract.pdf'))->toBe('signed');

    $this->privateDisk->assertMissing('media/contract.pdf');
});

it('only makes documents private', function (): void {
    $image = Media::factory()->create(['type' => MediaType::IMAGE]);

    new SetMediaPrivacyAction()->handle($image, true);

    expect($image->fresh()->is_private)->toBeFalse();
});

it('still marks a document private when its file is missing', function (): void {
    $media = Media::factory()->create(['type' => MediaType::DOCUMENT, 'source' => 'media/gone.pdf']);

    new SetMediaPrivacyAction()->handle($media, true);

    expect($media->fresh()->is_private)->toBeTrue();
});

it('links private documents to the admin instead of the public storage folder', function (): void {
    $media = privateDocument(thumbnail: 'media/contract_thumb.png');

    expect($media->url)->toBe(route('admin.media-file', $media))
        ->and($media->downloadUrl)->toBe(route('admin.media-file', ['media' => $media, 'download' => 1]))
        ->and($media->preview)->toBe(ImageService::placeholder());
});

it('deletes a private document from the private disk', function (): void {
    $media = privateDocument();

    expect($media->delete())->toBeTrue();

    $this->privateDisk->assertMissing('media/contract.pdf');
});

it('serves a private document only to signed-in staff', function (): void {
    $media = privateDocument();

    $this->get(route('admin.media-file', $media))->assertRedirect(route('login'));

    $this->actingAsAdmin();

    $inline = $this->get(route('admin.media-file', $media))->assertOk();
    $download = $this->get(route('admin.media-file', ['media' => $media, 'download' => 1]))->assertOk();

    expect($inline->streamedContent())->toBe('signed contract')
        ->and($inline->headers->get('Cache-Control'))->toContain('no-store')
        ->and($inline->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and($download->headers->get('Content-Disposition'))->toStartWith('attachment');
});

it('answers 404 when a private document file is missing', function (): void {
    $media = Media::factory()->private()->create(['source' => 'media/gone.pdf']);

    $this->actingAsAdmin()
        ->get(route('admin.media-file', $media))
        ->assertNotFound();
});

it('keeps private documents out of download blocks and record pages', function (): void {
    $media = privateDocument();
    $block = Block::factory()->for(Page::factory(), 'blockable')->create([
        'type' => BlockType::DOWNLOADS,
        'content' => ['files' => [['source' => $media->source, 'filename' => 'contract.pdf']]],
    ]);

    $type = RecordType::factory()->create(['fields' => [['key' => 'contract', 'type' => 'document', 'label' => ['en' => 'Contract']]]]);
    $record = Record::factory()->for($type, 'recordType')->create();
    $record->syncMediaForRole('contract', 'en', [['id' => $media->id]]);
    $record->load('media');

    expect($block->fileUrl('files.0'))->toBeNull()
        ->and($record->fieldMedia('contract', false))->toBeEmpty()
        ->and($record->hasFieldContent($type->fields[0]))->toBeFalse();
});

it('uploads privately when the library is opened for a private document field', function (): void {
    $file = UploadedFile::fake()->create('contract.pdf', 10, 'application/pdf');

    Livewire::test('admin.media-library')
        ->dispatch('select-media', target: 'contract', type: 'document', max: 1, media: null, private: true)
        ->assertSet('uploadPrivate', true)
        ->set('files', [$file])
        ->call('save', [[]]);

    $media = Media::query()->sole();

    expect($media->is_private)->toBeTrue();

    $this->privateDisk->assertExists($media->source);
});

it('switches a document between private and public from the library', function (): void {
    $this->publicDisk->put('media/contract.pdf', 'signed');
    $media = Media::factory()->create(['type' => MediaType::DOCUMENT, 'source' => 'media/contract.pdf']);

    Livewire::test('admin.media-library')
        ->call('selectMedia', $media)
        ->call('edit')
        ->assertSet('private', false)
        ->assertSee('Only signed-in staff can open it.')
        ->set('private', true)
        ->call('update')
        ->assertSee('Private');

    expect($media->fresh()->is_private)->toBeTrue();

    $this->privateDisk->assertExists('media/contract.pdf');
});

it('offers the private switch for documents only', function (): void {
    $image = Media::factory()->create(['type' => MediaType::IMAGE]);

    Livewire::test('admin.media-library')
        ->call('selectMedia', $image)
        ->call('edit')
        ->assertDontSee('Only signed-in staff can open it.');
});

it('asks the library for a private upload from a private document field', function (): void {
    Livewire::test('admin.media-selector', ['name' => 'contract', 'type' => 'document', 'locale' => 'en', 'private' => true])
        ->call('openLibrary')
        ->assertDispatched('select-media', private: true);
});

it('imports a document privately over MCP', function (): void {
    $root = storage_path('framework/testing/import-'.bin2hex(random_bytes(5)));
    File::ensureDirectoryExists($root);
    config()->set('wireup.media_import_path', $root);
    file_put_contents($root.'/contract.pdf', '%PDF-1.4 signed');

    WireUpServer::tool(UploadMediaTool::class, ['path' => 'contract.pdf', 'private' => true])
        ->assertOk()
        ->assertSee('"private":true');

    File::deleteDirectory($root);

    expect(Media::query()->sole()->is_private)->toBeTrue();
});
