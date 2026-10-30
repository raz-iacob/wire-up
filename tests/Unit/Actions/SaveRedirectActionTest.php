<?php

declare(strict_types=1);

use App\Actions\SaveRedirectAction;
use App\Enums\RedirectOrigin;
use App\Models\NotFoundPath;
use App\Models\Redirect;
use Illuminate\Validation\ValidationException;

it('saves a normalized redirect and clears its address from the not found log', function (): void {
    NotFoundPath::factory()->create(['path' => '/old-page']);

    $redirect = new SaveRedirectAction()->handle(' /Old-Page/ ', 'new-page', 302);

    expect($redirect->from)->toBe('/old-page')
        ->and($redirect->to)->toBe('/new-page')
        ->and($redirect->status_code)->toBe(302)
        ->and($redirect->origin)->toBe(RedirectOrigin::MANUAL)
        ->and(NotFoundPath::query()->exists())->toBeFalse();
});

it('updates the redirect that already starts from an address instead of adding another', function (): void {
    $existing = Redirect::factory()->create(['from' => '/old-page', 'to' => '/first']);

    $saved = new SaveRedirectAction()->handle('/old-page', '/second', origin: RedirectOrigin::IMPORT);

    expect($saved->id)->toBe($existing->id)
        ->and(Redirect::query()->sole()->to)->toBe('/second')
        ->and($saved->origin)->toBe(RedirectOrigin::IMPORT);
});

it('points earlier redirects straight at the new address so visitors take one hop', function (): void {
    $older = Redirect::factory()->create(['from' => '/oldest', 'to' => '/old']);

    new SaveRedirectAction()->handle('/old', '/new');

    expect($older->fresh()->to)->toBe('/new');
});

it('refuses redirects that cannot work, with a reason for each', function (string $from, string $to, string $field, string $message): void {
    expect(new SaveRedirectAction()->problems($from, $to))->toBe([$field => $message])
        ->and(fn (): Redirect => new SaveRedirectAction()->handle($from, $to))->toThrow(ValidationException::class, $message);
})->with([
    'homepage' => ['/', '/elsewhere', 'from', 'The homepage cannot be redirected.'],
    'everything' => ['/*', '/elsewhere/*', 'from', 'The homepage cannot be redirected.'],
    'admin' => ['/admin/pages', '/elsewhere', 'from', 'This address belongs to the admin and cannot be redirected.'],
    'too long' => ['/'.str_repeat('a', 500), '/elsewhere', 'from', 'The old address is too long.'],
    'not a web address' => ['/old', 'ftp://example.com/file', 'to', 'Enter a path starting with / or a full web address starting with https://.'],
    'broken web address' => ['/old', 'https://', 'to', 'Enter a path starting with / or a full web address starting with https://.'],
    'protocol relative' => ['/old', '//example.com', 'to', 'Enter a path starting with / or a full web address starting with https://.'],
    'wildcard target without wildcard source' => ['/old', '/new/*', 'to', 'Only an old address ending in /* can send visitors to one.'],
    'same address' => ['/Old/', '/old', 'to', 'The new address is the same as the old one.'],
    'same page with a query' => ['/old', '/old?ref=1', 'to', 'The new address is the same as the old one.'],
]);

it('accepts full web addresses, wildcards and query strings', function (): void {
    $action = new SaveRedirectAction();

    expect($action->problems('/forum/*', 'https://community.example.com/*'))->toBeEmpty()
        ->and($action->problems('/blog/*', '/news/*'))->toBeEmpty()
        ->and($action->problems('/product?id=7', '/product'))->toBeEmpty();
});

it('refuses an edit that would take an address another redirect already starts from', function (): void {
    Redirect::factory()->create(['from' => '/taken', 'to' => '/somewhere']);
    $edited = Redirect::factory()->create(['from' => '/mine', 'to' => '/elsewhere']);

    expect(new SaveRedirectAction()->problems('/taken', '/elsewhere', $edited))
        ->toBe(['from' => 'Another redirect already starts from this address.']);
});

it('refuses a redirect that would send visitors around in a loop', function (): void {
    Redirect::factory()->create(['from' => '/b', 'to' => '/c?x=2']);
    Redirect::factory()->create(['from' => '/c', 'to' => '/a']);

    expect(new SaveRedirectAction()->problems('/a', '/b'))->toBe(['to' => 'This would send visitors around in a loop.']);
});

it('follows a chain only until it leaves the site or ends', function (): void {
    Redirect::factory()->create(['from' => '/b', 'to' => 'https://example.com/a']);
    Redirect::factory()->create(['from' => '/d', 'to' => '/e']);

    $action = new SaveRedirectAction();

    expect($action->problems('/a', '/b'))->toBeEmpty()
        ->and($action->problems('/a', '/d'))->toBeEmpty();
});

it('ignores the redirect being edited when looking for a loop', function (): void {
    $edited = Redirect::factory()->create(['from' => '/b', 'to' => '/a']);

    expect(new SaveRedirectAction()->problems('/a', '/b', $edited))->toBeEmpty();
});

it('gives up following a chain after ten hops', function (): void {
    foreach (range(1, 11) as $hop) {
        Redirect::factory()->create(['from' => "/hop-{$hop}", 'to' => '/hop-'.($hop + 1)]);
    }

    expect(new SaveRedirectAction()->problems('/hop-12', '/hop-1'))->toBeEmpty();
});
