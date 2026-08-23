<?php

declare(strict_types=1);

use App\Actions\StoreMediaFileAction;
use App\Enums\MediaType;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake(config()->string('filesystems.media'));
    $this->scratch = sys_get_temp_dir().'/wireup-media-'.bin2hex(random_bytes(6));
    mkdir($this->scratch);
});

afterEach(function (): void {
    array_map(unlink(...), glob($this->scratch.'/*') ?: []);
    rmdir($this->scratch);
});

function storedScratchFile(string $name, string $contents): string
{
    $path = test()->scratch.'/'.$name;
    file_put_contents($path, $contents);

    return $path;
}

function storedPixelPng(): string
{
    return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==', true);
}

it('stores an image with its dimensions and detected type', function (): void {
    $media = resolve(StoreMediaFileAction::class)->handle(storedScratchFile('Photo Of A Cat.png', storedPixelPng()), 'Photo Of A Cat.png');

    expect($media->type)->toBe(MediaType::IMAGE)
        ->and($media->filename)->toBe('Photo Of A Cat.png')
        ->and($media->alt_text)->toBe('Photo Of A Cat')
        ->and($media->width)->toBe(1)
        ->and($media->height)->toBe(1)
        ->and($media->mime_type)->toBe('image/png')
        ->and($media->source)->toContain('photo-of-a-cat.png');

    Storage::disk(config()->string('filesystems.media'))->assertExists($media->source);
});

it('sanitises an svg and keeps its own mime type', function (): void {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect width="4" height="4"/></svg>';

    $media = resolve(StoreMediaFileAction::class)->handle(storedScratchFile('logo.svg', $svg), 'logo.svg');

    $stored = Storage::disk(config()->string('filesystems.media'))->get($media->source);

    expect($media->mime_type)->toBe('image/svg+xml')
        ->and($media->type)->toBe(MediaType::IMAGE)
        ->and($stored)->not->toContain('<script')
        ->and($stored)->toContain('<rect');
});

it('returns the existing media row when the same bytes are stored twice', function (): void {
    $action = resolve(StoreMediaFileAction::class);

    $first = $action->handle(storedScratchFile('a.png', storedPixelPng()), 'a.png');
    $second = $action->handle(storedScratchFile('b.png', storedPixelPng()), 'b.png');

    expect($second->id)->toBe($first->id);
});

it('stores a thumbnail supplied as a data uri', function (): void {
    $thumbnail = 'data:image/png;base64,'.base64_encode(storedPixelPng());

    $media = resolve(StoreMediaFileAction::class)->handle(
        storedScratchFile('clip.mp4', 'not really an mp4'),
        'clip.mp4',
        ['thumbnail' => $thumbnail, 'duration' => 12, 'mime_type' => 'video/mp4'],
    );

    expect($media->type)->toBe(MediaType::VIDEO)
        ->and($media->duration)->toBe(12)
        ->and($media->thumbnail)->toContain('_thumb.png');

    Storage::disk(config()->string('filesystems.media'))->assertExists((string) $media->thumbnail);
});

it('ignores a thumbnail that is not a data uri or carries no payload', function (string $thumbnail): void {
    $media = resolve(StoreMediaFileAction::class)->handle(
        storedScratchFile('doc-'.md5($thumbnail).'.png', storedPixelPng().$thumbnail),
        'doc.png',
        ['thumbnail' => $thumbnail],
    );

    expect($media->thumbnail)->toBeNull();
})->with([
    'not a data uri' => ['https://example.com/thumb.png'],
    'empty payload' => ['data:image/png;base64,'],
]);

it('prefers a caller supplied mime type over sniffing the file', function (): void {
    $media = resolve(StoreMediaFileAction::class)->handle(
        storedScratchFile('sheet.xlsx', 'PK'.str_repeat('x', 40)),
        'sheet.xlsx',
        ['mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    );

    expect($media->mime_type)->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->and($media->type)->toBe(MediaType::DOCUMENT);
});

it('fails with a friendly message when a heic image cannot be converted', function (): void {
    resolve(StoreMediaFileAction::class)->handle(storedScratchFile('broken.heic', 'nonsense bytes'), 'broken.heic');
})->throws(RuntimeException::class, 'The image could not be converted. Upload it as a JPG or PNG instead.');

it('corrects the orientation of a converted image', function (int $orientation, int $expectedWidth, int $expectedHeight): void {
    $source = new Imagick();
    $source->newImage(4, 2, 'red');
    $source->setImageFormat('jpeg');
    $jpeg = $source->getImageBlob();
    $source->clear();

    $exif = "Exif\0\0MM\x00\x2A\x00\x00\x00\x08\x00\x01\x01\x12\x00\x03\x00\x00\x00\x01".pack('n', $orientation)."\x00\x00\x00\x00\x00\x00";
    $segment = "\xFF\xE1".pack('n', mb_strlen($exif, '8bit') + 2).$exif;
    $tagged = substr_replace($jpeg, "\xFF\xD8".$segment, 0, 2);

    $media = resolve(StoreMediaFileAction::class)->handle(
        storedScratchFile("rotated-{$orientation}.heic", $tagged),
        "rotated-{$orientation}.heic",
    );

    expect($media->mime_type)->toBe('image/jpeg')
        ->and($media->filename)->toBe("rotated-{$orientation}.jpg")
        ->and($media->width)->toBe($expectedWidth)
        ->and($media->height)->toBe($expectedHeight);
})->with([
    'upright needs no rotation' => [Imagick::ORIENTATION_TOPLEFT, 4, 2],
    'upside down rotates 180' => [Imagick::ORIENTATION_BOTTOMRIGHT, 4, 2],
    'rotated right turns 90' => [Imagick::ORIENTATION_RIGHTTOP, 2, 4],
    'rotated left turns -90' => [Imagick::ORIENTATION_LEFTBOTTOM, 2, 4],
]);
