<?php

declare(strict_types=1);

use App\Enums\RedirectOrigin;
use App\Mcp\Servers\WireUpServer;
use App\Mcp\Tools\CreateRedirectsTool;
use App\Mcp\Tools\DeleteRedirectTool;
use App\Mcp\Tools\ListRedirectsTool;
use App\Models\NotFoundPath;
use App\Models\Redirect;

it('lists redirects with their use and the most requested missing addresses', function (): void {
    Redirect::factory()->create(['from' => '/old-team', 'to' => '/people', 'hits' => 7, 'last_hit_at' => now()]);
    Redirect::factory()->create(['from' => '/summer', 'to' => '/offers']);
    NotFoundPath::factory()->create(['path' => '/old-menu', 'hits' => 42]);

    WireUpServer::tool(ListRedirectsTool::class, [])
        ->assertOk()
        ->assertSee('"from":"/old-team"')
        ->assertSee('"hits":7')
        ->assertSee('"origin":"manual"')
        ->assertSee('"from":"/summer"')
        ->assertSee('"path":"/old-menu"');
});

it('narrows the redirect list to a search', function (): void {
    Redirect::factory()->create(['from' => '/old-team', 'to' => '/people']);
    Redirect::factory()->create(['from' => '/summer', 'to' => '/offers']);

    WireUpServer::tool(ListRedirectsTool::class, ['search' => 'peop', 'limit' => 5])
        ->assertOk()
        ->assertSee('old-team')
        ->assertDontSee('summer');
});

it('adds redirects in one call and reports the ones it skipped', function (): void {
    Redirect::factory()->create(['from' => '/existing', 'to' => '/before']);

    WireUpServer::tool(CreateRedirectsTool::class, ['redirects' => [
        ['from' => '/old-page', 'to' => '/new-page', 'status' => 302],
        ['from' => '/existing', 'to' => '/after'],
        ['from' => '/loop', 'to' => '/loop/'],
    ]])
        ->assertOk()
        ->assertSee('"added":1')
        ->assertSee('"updated":1')
        ->assertSee('"item":3')
        ->assertSee('The new address is the same as the old one.');

    $added = Redirect::query()->where('from', '/old-page')->sole();

    expect($added->status_code)->toBe(302)
        ->and($added->origin)->toBe(RedirectOrigin::MANUAL)
        ->and(Redirect::query()->where('from', '/existing')->sole()->to)->toBe('/after');
});

it('explains what a batch of redirects is missing', function (): void {
    WireUpServer::tool(CreateRedirectsTool::class, [])
        ->assertHasErrors(['Pass the redirects to add, such as [{"from": "/old-page", "to": "/new-page"}].']);

    WireUpServer::tool(CreateRedirectsTool::class, ['redirects' => [['from' => '/old', 'to' => '/new', 'status' => 307]]])
        ->assertHasErrors(['status must be 301 (permanent) or 302 (temporary).']);
});

it('deletes a redirect once confirmed', function (): void {
    $redirect = Redirect::factory()->create(['from' => '/old-team', 'to' => '/people']);

    WireUpServer::tool(DeleteRedirectTool::class, ['redirect' => $redirect->id, 'confirm' => true])
        ->assertOk()
        ->assertSee('old-team');

    expect(Redirect::query()->exists())->toBeFalse();
});

it('reports an unknown redirect id', function (): void {
    WireUpServer::tool(DeleteRedirectTool::class, ['redirect' => 999999, 'confirm' => true])
        ->assertHasErrors(['No redirect with id 999999. Use list-redirects to see them.']);
});

it('advertises what the redirect tools take', function (): void {
    expect(resolve(CreateRedirectsTool::class)->toArray()['inputSchema']['required'])->toBe(['redirects'])
        ->and(resolve(ListRedirectsTool::class)->toArray()['inputSchema']['properties'])->toHaveKeys(['search', 'limit']);
});
