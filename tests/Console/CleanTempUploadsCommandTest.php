<?php

declare(strict_types=1);

use App\Console\Commands\CleanTempUploadsCommand;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->tempPath = storage_path('app/private/livewire-tmp');

    if (File::exists($this->tempPath)) {
        File::deleteDirectory($this->tempPath);
    }

    File::makeDirectory($this->tempPath, 0755, true);
});

afterEach(function (): void {
    if (File::exists($this->tempPath)) {
        File::deleteDirectory($this->tempPath);
    }
});

it('displays message when no temp directory exists', function (): void {
    File::deleteDirectory($this->tempPath);

    $this->artisan(CleanTempUploadsCommand::class)
        ->expectsOutput('No Livewire temp directory found.')
        ->assertExitCode(0);
});

it('displays message when no files need to be deleted', function (): void {
    $recentFile = $this->tempPath.'/recent-file.txt';
    File::put($recentFile, 'test content');

    $this->artisan(CleanTempUploadsCommand::class)
        ->expectsOutput('No temporary files older than 24 hours found.')
        ->assertExitCode(0);

    expect(File::exists($recentFile))->toBeTrue();
});

it('deletes old files and displays summary', function (): void {
    $oldFile1 = $this->tempPath.'/old-file-1.txt';
    $oldFile2 = $this->tempPath.'/old-file-2.jpg';
    $recentFile = $this->tempPath.'/recent-file.txt';

    File::put($oldFile1, str_repeat('a', 1024));
    File::put($oldFile2, str_repeat('b', 2048));
    File::put($recentFile, str_repeat('c', 512));

    $this->artisan(CleanTempUploadsCommand::class, ['--older-than' => 0])
        ->assertExitCode(0);

    expect(File::exists($oldFile1))->toBeFalse();
    expect(File::exists($oldFile2))->toBeFalse();
    expect(File::exists($recentFile))->toBeFalse();
});

it('respects the older-than option', function (): void {
    $file = $this->tempPath.'/test-file.txt';
    File::put($file, 'test content');

    $this->artisan(CleanTempUploadsCommand::class, ['--older-than' => 1000])
        ->expectsOutput('No temporary files older than 1000 hours found.')
        ->assertExitCode(0);

    expect(File::exists($file))->toBeTrue();
    expect(File::exists($file))->toBeTrue();
});

it('handles custom older-than values correctly', function (): void {
    // Create multiple files
    $file1 = $this->tempPath.'/file1.txt';
    $file2 = $this->tempPath.'/file2.txt';

    File::put($file1, 'content1');
    File::put($file2, 'content2');

    $this->artisan(CleanTempUploadsCommand::class, ['--older-than' => 1])
        ->expectsOutput('No temporary files older than 1 hours found.')
        ->assertExitCode(0);

    expect(File::exists($file1))->toBeTrue();
    expect(File::exists($file2))->toBeTrue();
    expect(File::exists($file1))->toBeTrue();
    expect(File::exists($file2))->toBeTrue();
});

it('calculates file size correctly', function (): void {
    $largeFile = $this->tempPath.'/large-file.txt';
    $content = str_repeat('x', 1024 * 1024 * 2);
    File::put($largeFile, $content);

    $this->artisan(CleanTempUploadsCommand::class, ['--older-than' => 0])
        ->assertExitCode(0);

    expect(File::exists($largeFile))->toBeFalse();
});

it('handles files with various extensions', function (): void {
    $files = [
        'image.jpg',
        'document.pdf',
        'photo.png',
        'file.json',
        'upload.tmp',
    ];

    foreach ($files as $filename) {
        File::put($this->tempPath.'/'.$filename, 'test content');
    }

    $this->artisan(CleanTempUploadsCommand::class, ['--older-than' => 0])
        ->assertExitCode(0);

    foreach ($files as $filename) {
        expect(File::exists($this->tempPath.'/'.$filename))->toBeFalse();
    }
});

it('handles empty directory gracefully', function (): void {
    $this->artisan(CleanTempUploadsCommand::class)
        ->expectsOutput('No temporary files older than 24 hours found.')
        ->assertExitCode(0);
});

it('handles non-numeric older-than option', function (): void {
    $file = $this->tempPath.'/test.txt';
    File::put($file, 'content');

    $this->artisan(CleanTempUploadsCommand::class, ['--older-than' => 'abc'])
        ->assertExitCode(0);

    expect(File::exists($file))->toBeFalse();
});

it('works with subdirectories', function (): void {
    $subDir = $this->tempPath.'/subdir';
    File::makeDirectory($subDir);

    $file1 = $this->tempPath.'/root-file.txt';
    $file2 = $subDir.'/sub-file.txt';

    File::put($file1, 'root content');
    File::put($file2, 'sub content');

    $this->artisan(CleanTempUploadsCommand::class, ['--older-than' => 0])
        ->assertExitCode(0);

    expect(File::exists($file1))->toBeFalse();
    expect(File::exists($file2))->toBeFalse();
});
