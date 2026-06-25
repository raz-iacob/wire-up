<?php

declare(strict_types=1);

use App\Services\UploadLimit;

it('parses ini size strings into bytes', function (string $value, int $expected): void {
    expect(UploadLimit::parseIniSize($value))->toBe($expected);
})->with([
    'megabytes' => ['2M', 2 * 1024 * 1024],
    'lowercase megabytes' => ['8m', 8 * 1024 * 1024],
    'kilobytes' => ['512K', 512 * 1024],
    'gigabytes' => ['1G', 1024 * 1024 * 1024],
    'plain bytes' => ['1048576', 1048576],
    'empty' => ['', 0],
    'whitespace' => ['  16M  ', 16 * 1024 * 1024],
]);

it('reports a positive server upload ceiling', function (): void {
    expect(UploadLimit::serverMaxBytes())->toBeGreaterThan(0);
});

it('never exceeds the app cap or the server ceiling', function (): void {
    $serverKilobytes = intdiv(UploadLimit::serverMaxBytes(), 1024);

    expect(UploadLimit::cappedKilobytes(UploadLimit::VIDEO_MAX_KILOBYTES))
        ->toBeLessThanOrEqual(UploadLimit::VIDEO_MAX_KILOBYTES)
        ->toBeLessThanOrEqual($serverKilobytes);
});

it('returns the app cap when it is the smaller limit', function (): void {
    expect(UploadLimit::cappedKilobytes(1))->toBe(1);
});

it('enforces the app cap when no override is configured', function (): void {
    expect(UploadLimit::enforcedKilobytes(UploadLimit::VIDEO_MAX_KILOBYTES))->toBe(UploadLimit::VIDEO_MAX_KILOBYTES);
});

it('tightens the enforced limit to a configured override', function (): void {
    config()->set('media.max_upload_kilobytes', 12000);

    expect(UploadLimit::enforcedKilobytes(UploadLimit::VIDEO_MAX_KILOBYTES))->toBe(12000);
    expect(UploadLimit::cappedKilobytes(UploadLimit::VIDEO_MAX_KILOBYTES))->toBeLessThanOrEqual(12000);
});

it('ignores a zero or non-numeric override', function (): void {
    config()->set('media.max_upload_kilobytes', '0');
    expect(UploadLimit::enforcedKilobytes(5000))->toBe(5000);

    config()->set('media.max_upload_kilobytes', 'nonsense');
    expect(UploadLimit::enforcedKilobytes(5000))->toBe(5000);
});
