<?php

declare(strict_types=1);

use App\Actions\ReadWebpageAction;
use App\Mcp\Servers\WireUpServer;
use App\Mcp\Tools\ReadWebpageTool;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

function fakeSite(): void
{
    $home = '<!doctype html><html><head><title>Acme Home</title><meta name="description" content="We build great things"></head>'
        .'<body><nav><a href="/about">About</a><a href="/services">Services</a></nav>'
        .'<h1>Welcome to Acme</h1><p>We are a great company.</p>'
        .'<img src="/img/logo.png" alt="Acme Logo">'
        .'<a href="https://external.example/partner">Partner</a></body></html>';

    $about = '<!doctype html><html><head><title>About Acme</title></head><body><h1>About</h1><p>Founded in 2020.</p></body></html>';
    $services = '<!doctype html><html><head><title>Our Services</title></head><body><h2>Services</h2><ul><li>Consulting</li><li>Support</li></ul></body></html>';

    Http::fake([
        'acme.example/about*' => Http::response($about, 200, ['Content-Type' => 'text/html']),
        'acme.example/services*' => Http::response($services, 200, ['Content-Type' => 'text/html']),
        'acme.example*' => Http::response($home, 200, ['Content-Type' => 'text/html']),
    ]);
}

it('reads a page and extracts title, content, images and nav links', function (): void {
    fakeSite();

    $result = resolve(ReadWebpageAction::class)->handle('https://acme.example/', 1);
    $home = $result['pages'][0];

    expect($home['title'])->toBe('Acme Home')
        ->and($home['description'])->toBe('We build great things')
        ->and($home['content'])->toContain('# Welcome to Acme')
        ->and($home['content'])->toContain('We are a great company.')
        ->and($home['images'])->toContain(['src' => 'https://acme.example/img/logo.png', 'alt' => 'Acme Logo'])
        ->and(collect($home['nav_links'])->pluck('url'))->toContain('https://acme.example/about', 'https://acme.example/services');
});

it('crawls same-domain pages but not external ones', function (): void {
    fakeSite();

    $result = resolve(ReadWebpageAction::class)->handle('https://acme.example/', 8);

    expect($result['page_count'])->toBe(3)
        ->and(collect($result['pages'])->pluck('title')->all())
        ->toEqualCanonicalizing(['Acme Home', 'About Acme', 'Our Services'])
        ->and(collect($result['pages'])->pluck('url'))->not->toContain('https://external.example/partner');
});

it('honours the max_pages limit', function (): void {
    fakeSite();

    $result = resolve(ReadWebpageAction::class)->handle('https://acme.example/', 2);

    expect($result['page_count'])->toBe(2);
});

it('returns the crawl result through the tool', function (): void {
    fakeSite();

    WireUpServer::tool(ReadWebpageTool::class, ['url' => 'https://acme.example/', 'max_pages' => 3])
        ->assertOk()
        ->assertSee('Acme Home')
        ->assertSee('About Acme');
});

it('resolves every url form and excludes chrome and empty content', function (): void {
    $home = '<!doctype html><html><head><title>Acme</title></head><body>'
        .'<h1>Home</h1><h2>Section</h2><h3>Sub</h3><h4>Detail</h4>'
        .'<p>Body copy.</p><blockquote>A wise quote</blockquote><p></p>'
        .'<img src="/img/logo.png" alt="Logo">'
        .'<img src="//cdn.acme.example/banner.jpg" alt="Banner">'
        .'<a href="team.html">Team</a>'
        .'<a href="../up/deeper">Up</a>'
        .'<a href="./here">Here</a>'
        .'<a href="mailto:x@acme.example">Mail</a>'
        .'<a href="/files/brochure.pdf">Brochure</a>'
        .'<a href="https://external.example/x">External</a>'
        .'<footer><p>Footer note</p><a href="/privacy">Privacy</a></footer>'
        .'</body></html>';

    Http::fake(['acme.example*' => Http::response($home, 200, ['Content-Type' => 'text/html'])]);

    $page = resolve(ReadWebpageAction::class)->handle('https://acme.example/', 1)['pages'][0];

    expect(collect($page['images'])->pluck('src'))
        ->toContain('https://acme.example/img/logo.png', 'https://cdn.acme.example/banner.jpg')
        ->and($page['content'])->toContain('Body copy.')
        ->and($page['content'])->toContain('### Sub')
        ->and($page['content'])->toContain('#### Detail')
        ->and($page['content'])->toContain('> A wise quote')
        ->and($page['content'])->not->toContain('Footer note')
        ->and(collect($page['nav_links'])->pluck('text'))->toContain('Privacy');
});

it('follows a redirect and falls back to the h1 for the title', function (): void {
    Http::fake([
        'acme.example/old*' => Http::response('', 301, ['Location' => 'https://acme.example/new']),
        'acme.example/new*' => Http::response('<html><body><h1>Fresh Page</h1></body></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    $result = resolve(ReadWebpageAction::class)->handle('https://acme.example/old', 1);

    expect($result['pages'][0]['title'])->toBe('Fresh Page');
});

it('skips crawled pages that are not html or fail to load', function (): void {
    Http::fake([
        'acme.example/data*' => Http::response('{}', 200, ['Content-Type' => 'application/json']),
        'acme.example/boom*' => fn () => throw new ConnectionException('down'),
        'acme.example*' => Http::response('<html><head><title>Home</title></head><body><a href="/data">Data</a><a href="/boom">Boom</a></body></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    $result = resolve(ReadWebpageAction::class)->handle('https://acme.example/', 8);

    expect($result['page_count'])->toBe(1)
        ->and(collect($result['pages'])->pluck('url'))->not->toContain('https://acme.example/data', 'https://acme.example/boom');
});

it('refuses to read internal or private addresses', function (): void {
    Http::fake();

    WireUpServer::tool(ReadWebpageTool::class, ['url' => 'http://169.254.169.254/'])
        ->assertHasErrors()
        ->assertSee('public internet addresses');

    Http::assertNothingSent();
});

it('errors when the page cannot be read', function (): void {
    Http::fake(['*' => Http::response('nope', 500)]);

    WireUpServer::tool(ReadWebpageTool::class, ['url' => 'https://acme.example/'])
        ->assertHasErrors()
        ->assertSee('Could not read');
});

it('validates the url', function (): void {
    WireUpServer::tool(ReadWebpageTool::class, ['url' => 'not-a-url'])
        ->assertHasErrors()
        ->assertSee('valid http(s) URL');
});

it('advertises the read-webpage tool with its schema', function (): void {
    $advertised = resolve(ReadWebpageTool::class)->toArray();

    expect($advertised['name'])->toBe('read-webpage')
        ->and($advertised['inputSchema']['required'])->toBe(['url'])
        ->and($advertised['inputSchema']['properties'])->toHaveKeys(['url', 'max_pages']);
});
