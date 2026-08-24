<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Mcp\Servers\WireUpServer;
use App\Mcp\Tools\RenderPageTool;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use App\Services\PageScreenshot;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

function onePixel(): string
{
    return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==', true);
}

function livePage(string $slug = 'rendered'): Page
{
    $page = Page::factory()->create([
        'title' => 'Rendered Page',
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'metadata' => ['published_locales' => ['en']],
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => $slug]);

    return $page;
}

function fakeScreenshotProcess(): void
{
    Process::fake([
        '*' => function (PendingProcess $process) {
            $target = collect((array) $process->command)
                ->first(fn (string $part): bool => str_ends_with($part, '.png'));

            if (is_string($target)) {
                File::ensureDirectoryExists(dirname($target));
                file_put_contents($target, onePixel());
            }

            return Process::result('Captured screenshot');
        },
    ]);
}

it('renders a published page and returns an image', function (): void {
    fakeScreenshotProcess();

    $page = livePage();

    WireUpServer::tool(RenderPageTool::class, ['page' => $page->id])
        ->assertOk()
        ->assertSee(base64_encode(onePixel()));

    Process::assertRan(fn (PendingProcess $process): bool => str_contains(implode(' ', (array) $process->command), 'rendered'));
});

it('renders at the requested viewport and full page', function (): void {
    fakeScreenshotProcess();

    WireUpServer::tool(RenderPageTool::class, ['path' => '/', 'viewport' => 'mobile', 'full_page' => true])
        ->assertOk();

    Process::assertRan(function (PendingProcess $process): bool {
        $line = implode(' ', (array) $process->command);

        return str_contains($line, '390,844') && str_contains($line, '--full-page');
    });
});

it('renders a draft page through a signed preview link', function (): void {
    fakeScreenshotProcess();

    $page = Page::factory()->create(['status' => ContentStatus::DRAFT, 'metadata' => ['published_locales' => []]]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'draft-shot']);

    WireUpServer::tool(RenderPageTool::class, ['page' => $page->id])->assertOk();

    Process::assertRan(fn (PendingProcess $process): bool => collect((array) $process->command)
        ->contains(fn (string $part): bool => str_contains($part, '/draft-shot') && str_contains($part, 'signature=')));
});

it('renders a draft record through a signed preview link', function (): void {
    fakeScreenshotProcess();

    $type = RecordType::factory()->create(['key' => 'guide', 'slug_prefix' => 'guides']);
    $record = Record::factory()->create([
        'record_type_id' => $type->id,
        'title' => ['en' => 'Draft Guide'],
        'status' => ContentStatus::DRAFT,
        'metadata' => ['published_locales' => []],
    ]);
    $record->setSlugs();

    WireUpServer::tool(RenderPageTool::class, ['record' => $record->id])->assertOk();

    Process::assertRan(fn (PendingProcess $process): bool => collect((array) $process->command)
        ->contains(fn (string $part): bool => str_contains($part, '/guides/') && str_contains($part, 'signature=')));
});

it('reports unknown ids', function (string $key, string $expected): void {
    WireUpServer::tool(RenderPageTool::class, [$key => 999999])
        ->assertHasErrors()
        ->assertSee($expected);
})->with([
    'page' => ['page', 'No page with id 999999'],
    'record' => ['record', 'No record with id 999999'],
]);

it('needs exactly one target', function (array $arguments): void {
    WireUpServer::tool(RenderPageTool::class, $arguments)
        ->assertHasErrors(['Pass exactly one of "page", "record" or "path" to render.']);
})->with([
    'none' => [[]],
    'two' => [['page' => 1, 'path' => '/']],
]);

it('rejects a path that does not start with a slash', function (): void {
    WireUpServer::tool(RenderPageTool::class, ['path' => 'about'])
        ->assertHasErrors(['The path must start with a slash, e.g. "/about".']);
});

it('explains how to install the browser when it is missing', function (): void {
    Process::fake(['*' => Process::result(
        output: '',
        errorOutput: 'browserType.launch: Executable doesn\'t exist at /root/.cache/ms-playwright/chromium-1234/chrome. Looks like Playwright was just installed or updated. Please run "npx playwright install" to download new browsers.',
        exitCode: 1,
    )]);

    WireUpServer::tool(RenderPageTool::class, ['path' => '/'])
        ->assertHasErrors()
        ->assertSee('npx playwright install chromium');
});

it('explains how to install playwright itself when it is missing', function (): void {
    Process::fake(['*' => Process::result(output: '', errorOutput: 'sh: npx: command not found', exitCode: 127)]);

    WireUpServer::tool(RenderPageTool::class, ['path' => '/'])
        ->assertHasErrors()
        ->assertSee('npm ci');
});

it('surfaces any other browser failure with its output', function (): void {
    Process::fake(['*' => Process::result(output: '', errorOutput: 'net::ERR_CONNECTION_REFUSED', exitCode: 1)]);

    WireUpServer::tool(RenderPageTool::class, ['path' => '/'])
        ->assertHasErrors()
        ->assertSee('ERR_CONNECTION_REFUSED');
});

it('reports when the browser succeeds but writes no file', function (): void {
    Process::fake(['*' => Process::result('done')]);

    WireUpServer::tool(RenderPageTool::class, ['path' => '/'])
        ->assertHasErrors()
        ->assertSee('produced no image');
});

it('cleans up the screenshot file it captured', function (): void {
    fakeScreenshotProcess();

    WireUpServer::tool(RenderPageTool::class, ['path' => '/'])->assertOk();

    expect(File::files(storage_path('framework/screenshots')))->toBeEmpty();
});

it('falls back to the desktop viewport for an unknown size', function (): void {
    fakeScreenshotProcess();

    resolve(PageScreenshot::class)->capture('https://example.test', 'enormous');

    Process::assertRan(fn (PendingProcess $process): bool => str_contains(implode(' ', (array) $process->command), '1280,900'));
});

it('advertises render-page with its arguments', function (): void {
    $advertised = resolve(RenderPageTool::class)->toArray();

    expect($advertised['name'])->toBe('render-page')
        ->and($advertised['inputSchema']['properties'])->toHaveKeys(['page', 'record', 'path', 'viewport', 'full_page']);
});

it('renders a published record at its public url', function (): void {
    fakeScreenshotProcess();

    $type = RecordType::factory()->create(['key' => 'guide', 'slug_prefix' => 'guides']);
    $record = Record::factory()->create([
        'record_type_id' => $type->id,
        'title' => ['en' => 'Rendered Guide'],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'metadata' => ['published_locales' => ['en']],
    ]);
    $record->setSlugs();

    WireUpServer::tool(RenderPageTool::class, ['record' => $record->id])
        ->assertOk()
        ->assertSee(base64_encode(onePixel()));

    Process::assertRan(fn (PendingProcess $process): bool => str_contains(implode(' ', (array) $process->command), 'guides/'));
});

it('says plainly when there is no web address to render yet', function (): void {
    $page = Page::factory()->create(['status' => ContentStatus::DRAFT, 'metadata' => ['published_locales' => []]]);

    WireUpServer::tool(RenderPageTool::class, ['page' => $page->id])
        ->assertHasErrors()
        ->assertSee('has no web address yet');
});
