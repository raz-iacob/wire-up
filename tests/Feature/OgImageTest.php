<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Enums\MediaType;
use App\Jobs\GenerateOgImage;
use App\Models\Media;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use App\Models\Settings;
use App\Services\OgImageService;
use Illuminate\Process\PendingProcess;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('wireup.og_path', storage_path('framework/testing/og-'.Str::random(8)));
    config()->set('wireup.og_images', true);
    Queue::fake();
});

afterEach(function (): void {
    File::deleteDirectory(config()->string('wireup.og_path'));
});

function sharedPage(string $slug = 'shared', string $title = 'A page worth sharing'): Page
{
    $page = Page::factory()->create([
        'title' => $title,
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'metadata' => ['published_locales' => ['en']],
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => $slug]);

    return $page;
}

function fakeOgBrowser(): void
{
    Process::fake(['*' => function (PendingProcess $process) {
        $target = collect((array) $process->command)->first(fn (string $part): bool => str_ends_with($part, '.png'));

        if (is_string($target)) {
            File::ensureDirectoryExists(dirname($target));
            File::put($target, (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==', true));
        }

        return Process::result('captured');
    }]);
}

it('queues a share image when published content is saved', function (): void {
    sharedPage();

    Queue::assertPushed(GenerateOgImage::class);
});

it('queues nothing for content that is not published anywhere', function (): void {
    Page::factory()->create(['status' => ContentStatus::DRAFT, 'metadata' => ['published_locales' => []]]);

    Queue::assertNotPushed(GenerateOgImage::class);
});

it('captures the card at the size social networks expect', function (): void {
    fakeOgBrowser();

    expect(resolve(OgImageService::class)->generate(sharedPage(), 'en'))->toBeTrue();

    Process::assertRan(fn (PendingProcess $process): bool => str_contains(implode(' ', (array) $process->command), '1200,630'));
});

it('reuses the cached card instead of opening a browser again', function (): void {
    fakeOgBrowser();

    $page = sharedPage();
    $images = resolve(OgImageService::class);

    $images->generate($page, 'en');
    Process::fake(['*' => Process::result(output: '', errorOutput: 'should not run', exitCode: 1)]);

    expect($images->generate($page, 'en'))->toBeTrue();
});

it('rebuilds the card when the title changes and drops the old one', function (): void {
    fakeOgBrowser();

    $page = sharedPage();
    $images = resolve(OgImageService::class);
    $images->generate($page, 'en');

    $first = $images->file($page, 'en');

    $page->update(['title' => 'A different headline']);
    $images->generate($page->fresh(), 'en');

    $second = $images->file($page->fresh(), 'en');

    expect($second)->not->toBe($first)
        ->and(File::glob(config()->string('wireup.og_path').'/page-'.$page->id.'/en-*.png'))->toHaveCount(1);
});

it('serves the generated card over a public url', function (): void {
    fakeOgBrowser();

    $page = sharedPage();
    resolve(OgImageService::class)->generate($page, 'en');

    $this->get('/shared')->assertOk()->assertSee('/og/page/'.$page->id.'/en.png', false);

    $this->get(route('og.show', ['type' => 'page', 'id' => $page->id, 'locale' => 'en']))
        ->assertOk()
        ->assertHeader('content-type', 'image/png');
});

it('refuses the card for content that is not live', function (): void {
    fakeOgBrowser();

    $page = sharedPage('draft-card');
    resolve(OgImageService::class)->generate($page, 'en');
    $page->update(['status' => ContentStatus::DRAFT, 'metadata' => ['published_locales' => []]]);

    $this->get(route('og.show', ['type' => 'page', 'id' => $page->id, 'locale' => 'en']))->assertNotFound();
});

it('keeps an uploaded share image ahead of the generated one', function (): void {
    fakeOgBrowser();

    $page = sharedPage('own-image');
    resolve(OgImageService::class)->generate($page, 'en');

    $media = Media::factory()->create(['type' => MediaType::IMAGE]);
    $page->media()->attach($media->id, [
        'role' => 'og_image',
        'locale' => 'en',
        'position' => 0,
        'crop' => ['desktop' => ['crop_w' => 1200, 'crop_h' => 630, 'crop_x' => 0, 'crop_y' => 0]],
    ]);

    $this->get('/own-image')->assertOk()->assertDontSee('/og/page/', false);
});

it('falls back to the site default when no browser can make a card', function (): void {
    Settings::set(['default_og_image' => ['source' => 'fallback.jpg', 'crop' => ['default' => ['crop_w' => 1200, 'crop_h' => 630, 'crop_x' => 0, 'crop_y' => 0]]]]);
    Process::fake(['*' => Process::result(output: '', errorOutput: 'sh: npx: command not found', exitCode: 127)]);

    $page = sharedPage('no-browser');

    expect(resolve(OgImageService::class)->generate($page, 'en'))->toBeFalse();

    $this->get('/no-browser')->assertOk()->assertDontSee('/og/page/', false)->assertSee('fallback.jpg', false);
});

it('never puts the generated card in the media library', function (): void {
    fakeOgBrowser();

    resolve(OgImageService::class)->generate(sharedPage(), 'en');

    expect(Media::query()->count())->toBe(0);
});

it('leaves no temporary html behind', function (): void {
    fakeOgBrowser();

    resolve(OgImageService::class)->generate(sharedPage(), 'en');

    expect(File::glob(config()->string('wireup.og_path').'/*.html'))->toBe([]);
});

it('skips content with no usable title', function (): void {
    $page = sharedPage('untitled', '');

    expect(resolve(OgImageService::class)->generate($page, 'en'))->toBeFalse();
});

it('forgets the cards when the content is deleted', function (): void {
    fakeOgBrowser();

    $page = sharedPage('to-delete');
    resolve(OgImageService::class)->generate($page, 'en');

    $directory = config()->string('wireup.og_path').'/page-'.$page->id;
    expect(File::isDirectory($directory))->toBeTrue();

    $page->delete();

    expect(File::isDirectory($directory))->toBeFalse();
});

it('generates for every published page and record from the command', function (): void {
    fakeOgBrowser();

    $first = sharedPage('one', 'First page');
    $second = sharedPage('two', 'Second page');

    $this->artisan('wireup:og:generate')->assertSuccessful();

    $images = resolve(OgImageService::class);

    expect($images->file($first, 'en'))->not->toBeNull()
        ->and($images->file($second, 'en'))->not->toBeNull();
});

it('runs the job for each published locale', function (): void {
    fakeOgBrowser();

    $page = sharedPage('jobbed');

    new GenerateOgImage('page', (int) $page->id)->handle(resolve(OgImageService::class));

    expect(resolve(OgImageService::class)->file($page->fresh(), 'en'))->not->toBeNull();
});

function sharedRecord(string $title = 'A record worth sharing'): Record
{
    $type = RecordType::factory()->create(['key' => 'guide', 'slug_prefix' => 'guides', 'fields' => []]);

    $record = Record::factory()->create([
        'record_type_id' => $type->id,
        'title' => ['en' => $title],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'metadata' => ['published_locales' => ['en']],
    ]);
    $record->setSlugs();

    return $record;
}

it('makes and serves a card for a record too', function (): void {
    fakeOgBrowser();

    $record = sharedRecord();

    expect(resolve(OgImageService::class)->generate($record, 'en'))->toBeTrue();

    $this->get(route('og.show', ['type' => 'record', 'id' => $record->id, 'locale' => 'en']))
        ->assertOk()
        ->assertHeader('content-type', 'image/png');
});

it('runs the job for a record', function (): void {
    fakeOgBrowser();

    $record = sharedRecord();

    new GenerateOgImage('record', (int) $record->id)->handle(resolve(OgImageService::class));

    expect(resolve(OgImageService::class)->file($record->fresh(), 'en'))->not->toBeNull();
});

it('does nothing when the job runs for content that is gone', function (): void {
    Process::fake(['*' => Process::result(output: '', errorOutput: 'should not run', exitCode: 1)]);

    new GenerateOgImage('page', 999999)->handle(resolve(OgImageService::class));

    Process::assertNothingRan();
});

it('keeps one job per piece of content on the queue', function (): void {
    $job = new GenerateOgImage('page', 7);

    expect($job->middleware())->toHaveCount(1)
        ->and($job->middleware()[0])->toBeInstanceOf(WithoutOverlapping::class);
});

it('rebuilds every card when the command is forced', function (): void {
    fakeOgBrowser();

    $page = sharedPage('forced', 'Forced page');
    $images = resolve(OgImageService::class);
    $images->generate($page, 'en');

    $before = $images->file($page, 'en');

    $this->artisan('wireup:og:generate', ['--force' => true])->assertSuccessful();

    expect($images->file($page->fresh(), 'en'))->not->toBeNull()
        ->and(basename((string) $before))->toBe(basename((string) $images->file($page->fresh(), 'en')));
});

it('reports what it had to skip', function (): void {
    Process::fake(['*' => Process::result(output: '', errorOutput: 'sh: npx: command not found', exitCode: 127)]);

    sharedPage('skipped', 'Cannot be drawn');

    $this->artisan('wireup:og:generate')
        ->expectsOutputToContain('Skipped')
        ->assertSuccessful();
});
