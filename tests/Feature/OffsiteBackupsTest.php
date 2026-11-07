<?php

declare(strict_types=1);

use App\Console\Commands\FetchBackupCommand;
use App\Enums\ActivityEvent;
use App\Jobs\CopyBackupOffsite;
use App\Models\Activity;
use App\Services\BackupService;
use App\Services\OffsiteBackupService;
use Aws\Command;
use Aws\Exception\AwsException;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use League\Flysystem\UnableToReadFile;
use Tests\Support\FakeOffsiteStorage;

beforeEach(function (): void {
    config()->set('wireup.backup_path', storage_path('framework/testing/offsite-'.Str::random(8)));
    File::ensureDirectoryExists(config()->string('wireup.backup_path'));
});

afterEach(function (): void {
    File::deleteDirectory(config()->string('wireup.backup_path'));
});

function localBackup(string $name, string $contents = 'zip'): string
{
    $path = config()->string('wireup.backup_path').'/'.$name;
    File::put($path, $contents);

    return $path;
}

it('copies a backup into the folder and keeps only the newest off-site copies', function (): void {
    $disk = FakeOffsiteStorage::connect();
    config()->set('site.offsite_backups_to_keep', 3);

    $disk->put('example.com/backup-2026-01-01-030000-automatic.zip', 'old');
    $disk->put('example.com/backup-2026-01-02-030000-manual.zip', 'old');
    $disk->put('example.com/backup-2026-01-03-030000-automatic.zip', 'old');
    $disk->put('example.com/db-backup-2026-01-01-000000-before-v0.7.0.sqlite', 'kept');
    $disk->put('example.com/notes.txt', 'kept');
    $disk->put('elsewhere.org/backup-2020-01-01-030000-automatic.zip', 'another site');

    $result = resolve(OffsiteBackupService::class)->copyNewBackup(localBackup('backup-2026-01-04-101500-manual.zip', 'new backup'));

    expect($result)->toBeNull()
        ->and($disk->get('example.com/backup-2026-01-04-101500-manual.zip'))->toBe('new backup')
        ->and($disk->allFiles())->toEqualCanonicalizing([
            'example.com/backup-2026-01-02-030000-manual.zip',
            'example.com/backup-2026-01-03-030000-automatic.zip',
            'example.com/backup-2026-01-04-101500-manual.zip',
            'example.com/db-backup-2026-01-01-000000-before-v0.7.0.sqlite',
            'example.com/notes.txt',
            'elsewhere.org/backup-2020-01-01-030000-automatic.zip',
        ])
        ->and(resolve(OffsiteBackupService::class)->state())->toBeNull();
});

it('copies into the bucket root when no folder is set', function (): void {
    $disk = FakeOffsiteStorage::connect(['offsite_folder' => '']);

    resolve(OffsiteBackupService::class)->copyNewBackup(localBackup('backup-2026-01-04-101500-manual.zip'));

    expect($disk->allFiles())->toBe(['backup-2026-01-04-101500-manual.zip'])
        ->and(resolve(OffsiteBackupService::class)->location())->toBe('site-backups');
});

it('keeps the off-site copy when the backup is deleted on the server', function (): void {
    $disk = FakeOffsiteStorage::connect();
    $offsite = resolve(OffsiteBackupService::class);

    $offsite->copyNewBackup(localBackup('backup-2026-01-04-101500-manual.zip', 'abcd'));
    resolve(BackupService::class)->delete('backup-2026-01-04-101500-manual.zip');

    expect($disk->exists('example.com/backup-2026-01-04-101500-manual.zip'))->toBeTrue()
        ->and(array_map(fn (array $backup): array => Arr::only($backup, ['file', 'kind', 'bytes']), $offsite->files() ?? []))->toBe([['file' => 'backup-2026-01-04-101500-manual.zip', 'kind' => 'manual', 'bytes' => 4]]);
});

it('does nothing when off-site storage is not connected', function (): void {
    $offsite = resolve(OffsiteBackupService::class);

    expect($offsite->configured())->toBeFalse()
        ->and($offsite->copyNewBackup(localBackup('backup-2026-01-04-101500-manual.zip')))->toBeNull()
        ->and($offsite->files())->toBe([]);
});

it('records a failed copy with the storage provider’s reason until a copy works again', function (): void {
    FakeOffsiteStorage::connectRefusing('AccessDenied', 'Access Denied');
    $archive = localBackup('backup-2026-01-04-101500-manual.zip');

    $reason = resolve(OffsiteBackupService::class)->copyNewBackup($archive);

    expect($reason)->toBe('AccessDenied: Access Denied')
        ->and(resolve(OffsiteBackupService::class)->state())->toBe(['status' => 'failed', 'file' => 'backup-2026-01-04-101500-manual.zip', 'output' => 'AccessDenied: Access Denied'])
        ->and(Activity::query()->sole()->only(['event', 'subject_label', 'properties']))->toBe([
            'event' => ActivityEvent::BACKUP_OFFSITE_FAILED,
            'subject_label' => 'backup-2026-01-04-101500-manual.zip',
            'properties' => ['reason' => 'AccessDenied: Access Denied'],
        ]);

    FakeOffsiteStorage::connect();

    expect(resolve(OffsiteBackupService::class)->copyNewBackup($archive))->toBeNull()
        ->and(resolve(OffsiteBackupService::class)->state())->toBeNull();
});

it('explains failures that did not come from the storage provider in plain words', function (): void {
    expect(OffsiteBackupService::reason(new RuntimeException('  The network is down.  ')))->toBe('The network is down.')
        ->and(OffsiteBackupService::reason(new AwsException('Could not resolve host', new Command('PutObject'))))->toBe('Could not resolve host')
        ->and(OffsiteBackupService::reason(new RuntimeException('Unable to write file', previous: new AwsException('x', new Command('PutObject'), ['code' => 'NoSuchBucket', 'message' => 'The bucket does not exist']))))
        ->toBe('NoSuchBucket: The bucket does not exist');
});

it('lists off-site copies newest first and remembers the list for a while', function (): void {
    $disk = FakeOffsiteStorage::connect();
    $disk->put('example.com/backup-2026-01-01-030000-automatic.zip', 'a');
    $disk->put('example.com/backup-2026-01-03-030000-manual.zip', 'bbb');

    $offsite = resolve(OffsiteBackupService::class);

    expect(array_column($offsite->files() ?? [], 'file'))->toBe(['backup-2026-01-03-030000-manual.zip', 'backup-2026-01-01-030000-automatic.zip']);

    $disk->put('example.com/backup-2026-01-05-030000-automatic.zip', 'c');

    expect($offsite->files())->toHaveCount(2);

    $offsite->copyNewBackup(localBackup('backup-2026-01-06-030000-manual.zip'));

    expect($offsite->files())->toHaveCount(4);
});

it('shows nothing instead of failing when off-site storage cannot be reached', function (): void {
    FakeOffsiteStorage::connectRefusing('InvalidAccessKeyId', 'The key does not exist');

    expect(resolve(OffsiteBackupService::class)->files())->toBeNull();
});

it('downloads the newest or a named off-site copy into the backups folder', function (): void {
    $disk = FakeOffsiteStorage::connect();
    $disk->put('example.com/backup-2026-01-01-030000-automatic.zip', 'older');
    $disk->put('example.com/backup-2026-01-03-030000-manual.zip', 'newest');
    $offsite = resolve(OffsiteBackupService::class);

    expect($offsite->fetch())->toBe('backup-2026-01-03-030000-manual.zip')
        ->and(File::get(config()->string('wireup.backup_path').'/backup-2026-01-03-030000-manual.zip'))->toBe('newest')
        ->and($offsite->fetch('backup-2026-01-01-030000-automatic.zip'))->toBe('backup-2026-01-01-030000-automatic.zip')
        ->and(fn (): string => $offsite->fetch('backup-2025-01-01-030000-manual.zip'))->toThrow(RuntimeException::class, 'There is no backup called backup-2025-01-01-030000-manual.zip in off-site storage.')
        ->and(fn (): mixed => $offsite->readStream('notes.txt'))->toThrow(RuntimeException::class, 'That is not a backup file.')
        ->and(fn (): mixed => $offsite->readStream('db-backup-2026-01-01-000000.sqlite'))->toThrow(RuntimeException::class, 'That is not a backup file.');

    $disk->deleteDirectory('example.com');

    expect(fn (): string => $offsite->fetch())->toThrow(RuntimeException::class, 'There are no backups in off-site storage yet.');
});

it('says when a backup cannot be saved after downloading it', function (): void {
    $disk = FakeOffsiteStorage::connect();
    $disk->put('example.com/backup-2026-01-03-030000-manual.zip', 'newest');
    File::ensureDirectoryExists(config()->string('wireup.backup_path').'/backup-2026-01-03-030000-manual.zip');

    expect(fn (): string => @resolve(OffsiteBackupService::class)->fetch())->toThrow(RuntimeException::class, 'The backup could not be saved in');
});

it('says when an off-site copy cannot be read', function (): void {
    FakeOffsiteStorage::connectRefusing('NoSuchKey', 'The key does not exist');

    expect(fn (): mixed => resolve(OffsiteBackupService::class)->readStream('backup-2026-01-03-030000-manual.zip'))->toThrow(UnableToReadFile::class, 'The key does not exist');
});

it('marks a copy that has been going for too long as stalled', function (): void {
    $offsite = resolve(OffsiteBackupService::class);
    $offsite->markCopying('backup-2026-01-03-030000-manual.zip');

    expect($offsite->state()['status'] ?? null)->toBe('copying');

    $this->travel(71)->minutes();

    expect($offsite->state()['status'] ?? null)->toBe('stalled');
});

it('builds an S3 disk for the saved bucket', function (): void {
    FakeOffsiteStorage::settings();
    $offsite = new OffsiteBackupService(resolve(BackupService::class));

    $disk = $offsite->disk();

    expect($disk)->toBeInstanceOf(AwsS3V3Adapter::class)
        ->and($disk->getConfig())->toMatchArray(['bucket' => 'site-backups', 'region' => 'auto', 'endpoint' => 'https://account.r2.cloudflarestorage.com', 'use_path_style_endpoint' => true])
        ->and($offsite->diskConfig(['offsite_bucket' => 'aws-bucket', 'offsite_region' => '', 'offsite_endpoint' => '']))
        ->toMatchArray(['bucket' => 'aws-bucket', 'region' => 'auto', 'endpoint' => null, 'use_path_style_endpoint' => false]);
});

it('copies a backup again when asked to try again', function (): void {
    $disk = FakeOffsiteStorage::connect();
    localBackup('backup-2026-01-04-101500-manual.zip');
    resolve(OffsiteBackupService::class)->markCopying('backup-2026-01-04-101500-manual.zip');

    dispatch_sync(new CopyBackupOffsite('backup-2026-01-04-101500-manual.zip'));

    expect($disk->exists('example.com/backup-2026-01-04-101500-manual.zip'))->toBeTrue()
        ->and(resolve(OffsiteBackupService::class)->state())->toBeNull();
});

it('explains why trying again could not copy the backup', function (): void {
    FakeOffsiteStorage::connect();

    dispatch_sync(new CopyBackupOffsite('backup-2026-01-04-101500-manual.zip'));

    expect(resolve(OffsiteBackupService::class)->state()['output'] ?? null)->toBe('That backup is no longer on this server.');

    config()->set('site.offsite_bucket', '');
    localBackup('backup-2026-01-04-101500-manual.zip');

    dispatch_sync(new CopyBackupOffsite('backup-2026-01-04-101500-manual.zip'));

    expect(resolve(OffsiteBackupService::class)->state()['output'] ?? null)->toBe('Off-site storage is not connected.');

    new CopyBackupOffsite('backup-2026-01-04-101500-manual.zip')->failed(new RuntimeException('Timed out'));

    expect(resolve(OffsiteBackupService::class)->state()['output'] ?? null)->toBe('Timed out');

    new CopyBackupOffsite('backup-2026-01-04-101500-manual.zip')->failed(null);

    expect(resolve(OffsiteBackupService::class)->state()['output'] ?? null)->toBe('The copy stopped.')
        ->and(new CopyBackupOffsite('x')->uniqueId())->toBe('wireup-offsite-copy');
});

it('fetches an off-site copy from the command line', function (): void {
    $disk = FakeOffsiteStorage::connect();
    $disk->put('example.com/backup-2026-01-03-030000-manual.zip', 'newest');

    $this->artisan(FetchBackupCommand::class)
        ->expectsOutputToContain('Downloaded backup-2026-01-03-030000-manual.zip')
        ->expectsOutputToContain('php artisan wireup:backup:restore backup-2026-01-03-030000-manual.zip')
        ->assertExitCode(0);

    $this->artisan(FetchBackupCommand::class, ['file' => 'backup-2025-01-01-030000-manual.zip'])
        ->expectsOutputToContain('There is no backup called backup-2025-01-01-030000-manual.zip')
        ->assertExitCode(1);
});

it('asks for off-site storage to be connected before fetching', function (): void {
    $this->artisan(FetchBackupCommand::class)
        ->expectsOutputToContain('Off-site backups are not connected')
        ->assertExitCode(1);
});

it('lets staff who can change settings download a copy that is only off-site', function (): void {
    $disk = FakeOffsiteStorage::connect();
    $disk->put('example.com/backup-2026-01-03-030000-manual.zip', 'off-site zip');

    $this->actingAsAdmin();

    $response = $this->get(route('admin.backups-offsite-download', ['file' => 'backup-2026-01-03-030000-manual.zip']));

    $response->assertOk()->assertDownload('backup-2026-01-03-030000-manual.zip');

    expect($response->streamedContent())->toBe('off-site zip');

    $this->get(route('admin.backups-offsite-download', ['file' => 'notes.txt']))->assertNotFound();
    $this->get(route('admin.backups-offsite-download', ['file' => 'backup-2026-01-09-030000-manual.zip']))->assertNotFound();

    config()->set('site.offsite_bucket', '');

    $this->get(route('admin.backups-offsite-download', ['file' => 'backup-2026-01-03-030000-manual.zip']))->assertNotFound();
});
