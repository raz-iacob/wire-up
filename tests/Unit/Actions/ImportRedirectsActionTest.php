<?php

declare(strict_types=1);

use App\Actions\ImportRedirectsAction;
use App\Enums\RedirectOrigin;
use App\Models\Redirect;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

function redirectsCsv(string $contents): string
{
    $path = storage_path('framework/testing/redirects-'.Str::random(8).'.csv');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, $contents);

    return $path;
}

afterEach(function (): void {
    File::delete(File::glob(storage_path('framework/testing/redirects-*.csv')));
});

it('reads old and new addresses from a csv, skipping the heading and blank lines', function (): void {
    $path = redirectsCsv("\xEF\xBB\xBFFrom,To,Status\r\n/old-page, /new-page ,302\r\n\r\n/blog/*,/news/*,\n");

    expect(new ImportRedirectsAction()->csvRows($path))->toBe([
        2 => ['from' => '/old-page', 'to' => '/new-page', 'status' => 302],
        4 => ['from' => '/blog/*', 'to' => '/news/*', 'status' => null],
    ]);
});

it('reads a csv saved with semicolons', function (): void {
    $path = redirectsCsv("/old-page;/new-page\n/other;https://example.com/a,b\n");

    expect(new ImportRedirectsAction()->csvRows($path))->toBe([
        1 => ['from' => '/old-page', 'to' => '/new-page', 'status' => null],
        2 => ['from' => '/other', 'to' => 'https://example.com/a,b', 'status' => null],
    ]);
});

it('adds new redirects, updates existing ones and reports the rows it skipped', function (): void {
    Redirect::factory()->create(['from' => '/existing', 'to' => '/before']);

    $summary = new ImportRedirectsAction()->handle([
        2 => ['from' => '/old-page', 'to' => '/new-page', 'status' => 302],
        3 => ['from' => '/existing', 'to' => '/after', 'status' => 999],
        4 => ['from' => '/admin', 'to' => '/somewhere', 'status' => null],
        5 => ['from' => '/no-target', 'to' => '', 'status' => null],
    ], RedirectOrigin::IMPORT);

    expect($summary)->toBe([
        'added' => 1,
        'updated' => 1,
        'skipped' => [
            ['row' => 4, 'from' => '/admin', 'reason' => 'This address belongs to the admin and cannot be redirected.'],
            ['row' => 5, 'from' => '/no-target', 'reason' => 'Both the old and the new address are needed.'],
        ],
    ])
        ->and(Redirect::query()->where('from', '/old-page')->sole()->status_code)->toBe(302)
        ->and(Redirect::query()->where('from', '/existing')->sole()->status_code)->toBe(301)
        ->and(Redirect::query()->where('from', '/existing')->sole()->origin)->toBe(RedirectOrigin::IMPORT);
});
