<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\BackupService;
use App\Services\OffsiteBackupService;
use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\S3\Exception\S3Exception;
use Closure;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class FakeOffsiteStorage
{
    /**
     * @param  array<string, string>  $settings
     */
    public static function connect(array $settings = []): FilesystemAdapter
    {
        $disk = Storage::fake('offsite');

        self::use(fn (array $config): FilesystemAdapter => $disk, $settings);

        return $disk;
    }

    /**
     * @param  array<string, string>  $settings
     */
    public static function connectRefusing(string $code = 'AccessDenied', string $message = 'Access Denied', array $settings = []): void
    {
        self::use(function (array $config) use ($code, $message): FilesystemAdapter {
            $handler = new MockHandler;

            foreach (range(1, 20) as $attempt) {
                $handler->append(fn (CommandInterface $command): S3Exception => new S3Exception($message, $command, ['code' => $code, 'message' => $message]));
            }

            $disk = Storage::build([...$config, 'handler' => $handler]);

            return $disk instanceof FilesystemAdapter ? $disk : throw new RuntimeException('Expected a Flysystem disk.');
        }, $settings);
    }

    /**
     * @param  array<string, string>  $settings
     */
    public static function settings(array $settings = []): void
    {
        foreach ([
            'offsite_endpoint' => 'https://account.r2.cloudflarestorage.com',
            'offsite_region' => 'auto',
            'offsite_bucket' => 'site-backups',
            'offsite_folder' => 'example.com',
            'offsite_access_key' => 'access-key-id',
            'offsite_secret_key' => 'secret-access-key',
            ...$settings,
        ] as $key => $value) {
            config()->set("site.{$key}", $value);
        }
    }

    /**
     * @param  Closure(array<string, mixed>): FilesystemAdapter  $factory
     * @param  array<string, string>  $settings
     */
    private static function use(Closure $factory, array $settings): void
    {
        self::settings($settings);

        app()->instance(OffsiteBackupService::class, new OffsiteBackupService(resolve(BackupService::class), $factory));
    }
}
