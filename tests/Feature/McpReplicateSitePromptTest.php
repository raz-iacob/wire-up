<?php

declare(strict_types=1);

use App\Mcp\Prompts\ReplicateSitePrompt;
use App\Mcp\Servers\WireUpServer;

it('advertises the replicate-site prompt with its arguments', function (): void {
    $prompt = resolve(ReplicateSitePrompt::class)->toArray();

    expect($prompt['name'])->toBe('replicate-site')
        ->and(collect($prompt['arguments'])->pluck('name')->all())->toBe(['url', 'notes'])
        ->and(collect($prompt['arguments'])->firstWhere('name', 'url')['required'])->toBeTrue()
        ->and(collect($prompt['arguments'])->firstWhere('name', 'notes')['required'])->toBeFalse();
});

it('registers the prompt on the server', function (): void {
    expect(WireUpServer::PROMPTS)->toContain(ReplicateSitePrompt::class);
});

it('returns the replication playbook for a url', function (): void {
    WireUpServer::prompt(ReplicateSitePrompt::class, ['url' => 'https://example.com'])
        ->assertOk()
        ->assertSee('https://example.com')
        ->assertSee('read-webpage')
        ->assertSee('scaffold-site')
        ->assertSee('create-content-type')
        ->assertSee('publish-record');
});

it('weaves optional notes into the playbook', function (): void {
    WireUpServer::prompt(ReplicateSitePrompt::class, [
        'url' => 'https://example.com',
        'notes' => 'Only rebuild the homepage.',
    ])
        ->assertOk()
        ->assertSee('Only rebuild the homepage.');
});

it('requires a url', function (): void {
    WireUpServer::prompt(ReplicateSitePrompt::class, [])
        ->assertHasErrors(['Pass the "url" of the website you want to replicate.']);
});
