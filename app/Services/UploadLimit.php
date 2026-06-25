<?php

declare(strict_types=1);

namespace App\Services;

final class UploadLimit
{
    public const int IMAGE_MAX_KILOBYTES = 10240;

    public const int VIDEO_MAX_KILOBYTES = 307200;

    public static function cappedKilobytes(int $appCapKilobytes): int
    {
        return min(self::enforcedKilobytes($appCapKilobytes), intdiv(self::serverMaxBytes(), 1024));
    }

    public static function enforcedKilobytes(int $appCapKilobytes): int
    {
        $override = config('media.max_upload_kilobytes');

        return is_numeric($override) && (int) $override > 0
            ? min($appCapKilobytes, (int) $override)
            : $appCapKilobytes;
    }

    public static function serverMaxBytes(): int
    {
        $limits = array_filter([
            self::parseIniSize((string) ini_get('upload_max_filesize')),
            self::parseIniSize((string) ini_get('post_max_size')),
        ], fn (int $bytes): bool => $bytes > 0);

        return $limits === [] ? PHP_INT_MAX : min($limits);
    }

    public static function parseIniSize(string $value): int
    {
        $value = mb_trim($value);

        if ($value === '') {
            return 0;
        }

        $number = (int) $value;

        return match (mb_strtolower(mb_substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
