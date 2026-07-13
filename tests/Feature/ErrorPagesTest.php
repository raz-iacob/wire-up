<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Models\Page;
use App\Models\Settings;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

function renderErrorPage(int $status): string
{
    $response = resolve(ExceptionHandler::class)->render(Request::create('/anything'), new HttpException($status));

    expect($response->getStatusCode())->toBe($status);

    return (string) $response->getContent();
}

beforeEach(function (): void {
    $page = Page::factory()->create([
        'metadata' => ['published_locales' => ['en']],
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'title' => 'Error Home',
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'error-home']);
    Settings::set(['home_page_id' => $page->id]);
});

it('renders the branded 404 page with the site header and no footer', function (): void {
    $this->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertSee(__('Page not found'))
        ->assertSee(__('Error').' 404')
        ->assertSee('data-site-header', false)
        ->assertDontSee('data-site-footer', false);
});

it('renders the branded 403 page with the site header', function (): void {
    $html = renderErrorPage(403);

    expect($html)->toContain(__('Access denied'))
        ->toContain('data-site-header');
});

it('renders the self-contained 500 page without site chrome', function (): void {
    $html = renderErrorPage(500);

    expect($html)->toContain(__('Something went wrong'))
        ->toContain(config()->string('app.name'))
        ->not->toContain('data-site-header');
});

it('renders the self-contained 503 page without site chrome', function (): void {
    $html = renderErrorPage(503);

    expect($html)->toContain(__('Down for maintenance'))
        ->not->toContain('data-site-header');
});
