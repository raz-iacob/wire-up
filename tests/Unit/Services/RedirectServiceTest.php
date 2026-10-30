<?php

declare(strict_types=1);

use App\Models\Locale;
use App\Models\Redirect;
use App\Services\RedirectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

it('normalizes an old address to a lowercase path without slashes at the end', function (string $input, string $expected): void {
    expect(RedirectService::normalizeFrom($input))->toBe($expected);
})->with([
    'trailing slash and case' => ['  /About-Us/  ', '/about-us'],
    'no leading slash' => ['old/page', '/old/page'],
    'doubled slashes' => ['old//page///', '/old/page'],
    'full url on another site' => ['https://old.example.com/Blog/Post?b=2&a=1', '/blog/post?a=1&b=2'],
    'encoded characters' => ['/caf%C3%A9', '/café'],
    'wildcard keeps no query' => ['/blog/*?page=2', '/blog/*'],
    'homepage' => ['', '/'],
    'malformed url' => ['http:///broken', '/http:/broken'],
]);

it('keeps full web addresses as typed and normalizes paths as new addresses', function (): void {
    expect(RedirectService::normalizeTo(' https://example.com/New-Page '))->toBe('https://example.com/New-Page')
        ->and(RedirectService::normalizeTo('ftp://example.com/file'))->toBe('ftp://example.com/file')
        ->and(RedirectService::normalizeTo('//example.com/x'))->toBe('//example.com/x')
        ->and(RedirectService::normalizeTo('New-Page/'))->toBe('/new-page');
});

it('tells public addresses from the admin and system ones', function (string $path, bool $public): void {
    expect(RedirectService::isPublicPath($path))->toBe($public);
})->with([
    ['/old-page', true],
    ['/fr/admin', true],
    ['/admin/pages', false],
    ['/livewire/update', false],
    ['/livewire-3f2a/update', false],
    ['/img/w=200/photo.jpg', false],
    ['/storage/media/file.pdf', false],
]);

it('builds localized paths with the locale only outside the default language', function (): void {
    expect(RedirectService::localizedPath('en', 'guides', 'First-Steps'))->toBe('/guides/first-steps')
        ->and(RedirectService::localizedPath('fr', '', 'a-propos'))->toBe('/fr/a-propos')
        ->and(RedirectService::localizedPath('en'))->toBe('/');
});

it('matches nothing and releases nothing while the redirects table cannot be read', function (): void {
    Cache::shouldReceive('rememberForever')->andThrow(unreachableDatabase());

    $redirects = resolve(RedirectService::class);
    $redirects->release(['/old-page']);

    expect($redirects->match(Request::create('/old-page')))->toBeNull();
});

it('releases only the addresses a redirect starts from', function (): void {
    Redirect::factory()->create(['from' => '/old-page', 'to' => '/new-page']);
    Redirect::factory()->create(['from' => '/blog/*', 'to' => '/news/*']);
    $kept = Redirect::factory()->create(['from' => '/kept', 'to' => '/elsewhere']);

    $redirects = resolve(RedirectService::class);
    $redirects->release(['/old-page', '/blog/*', '/nothing-here']);
    $redirects->release([]);

    expect(Redirect::query()->pluck('id')->all())->toBe([$kept->id])
        ->and($redirects->match(Request::create('/old-page')))->toBeNull()
        ->and($redirects->match(Request::create('/kept')))->not->toBeNull();
});

it('releases a content type prefix in every active language', function (): void {
    Locale::query()->where('code', 'fr')->update(['active' => true]);
    Cache::forget('site-locales');

    Redirect::factory()->create(['from' => '/guides/*', 'to' => '/articles/*']);
    Redirect::factory()->create(['from' => '/fr/guides', 'to' => '/fr/articles']);
    Redirect::factory()->create(['from' => '/guides/kept-page', 'to' => '/somewhere']);

    resolve(RedirectService::class)->releasePrefix('guides');

    expect(Redirect::query()->pluck('from')->all())->toBe(['/guides/kept-page']);
});
