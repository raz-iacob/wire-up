<?php

declare(strict_types=1);

use App\Enums\MediaType;
use App\Mcp\Servers\WireUpServer;
use App\Mcp\Tools\UpdateMediaTool;
use App\Models\Media;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake(config()->string('filesystems.media'));
    Storage::fake(config()->string('filesystems.private_media'));
});

it('changes and clears the alt text of a file', function (): void {
    $media = Media::factory()->create(['type' => MediaType::IMAGE, 'mime_type' => 'image/jpeg', 'alt_text' => 'Old words']);

    WireUpServer::tool(UpdateMediaTool::class, ['media' => $media->id, 'alt_text' => 'A potter at the wheel'])
        ->assertOk()
        ->assertSee('"alt_text":"A potter at the wheel"');

    expect($media->refresh()->alt_text)->toBe('A potter at the wheel');

    WireUpServer::tool(UpdateMediaTool::class, ['media' => $media->id, 'alt_text' => ''])->assertOk();

    expect($media->refresh()->alt_text)->toBe('');
});

it('makes a document private and public again', function (): void {
    Storage::disk(config()->string('filesystems.media'))->put('media/contract.pdf', 'signed');
    $media = Media::factory()->create(['type' => MediaType::DOCUMENT, 'mime_type' => 'application/pdf', 'source' => 'media/contract.pdf']);

    WireUpServer::tool(UpdateMediaTool::class, ['media' => $media->id, 'private' => true])
        ->assertOk()
        ->assertSee('"private":true');

    expect($media->refresh()->is_private)->toBeTrue();
    Storage::disk(config()->string('filesystems.private_media'))->assertExists('media/contract.pdf');

    WireUpServer::tool(UpdateMediaTool::class, ['media' => $media->id, 'private' => false])->assertOk();

    expect($media->refresh()->is_private)->toBeFalse();
    Storage::disk(config()->string('filesystems.media'))->assertExists('media/contract.pdf');
});

it('refuses changes it cannot make', function (Closure $arguments, string $message): void {
    WireUpServer::tool(UpdateMediaTool::class, $arguments())->assertHasErrors([$message]);
})->with([
    'nothing to change' => [fn (): array => ['media' => 1], 'Pass "alt_text", "private" or both'],
    'unknown file' => [fn (): array => ['media' => 999999, 'alt_text' => 'x'], 'No media with id 999999.'],
    'private image' => [
        fn (): array => ['media' => Media::factory()->create(['type' => MediaType::IMAGE, 'mime_type' => 'image/png', 'filename' => 'photo.png'])->id, 'private' => true],
        'Only documents can be private, and "photo.png" is a file of type image.',
    ],
]);
