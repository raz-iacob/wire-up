<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\VisitorCounter;
use Illuminate\Support\Facades\DB;

/**
 * @param  array<string, mixed>  $overrides
 */
function visitorSession(string $id, array $overrides = []): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => null,
        'ip_address' => '10.0.0.1',
        'user_agent' => 'Mozilla/5.0 (Macintosh) AppleWebKit/537.36 Chrome/141.0 Safari/537.36',
        'payload' => '',
        'last_activity' => now()->getTimestamp(),
        ...$overrides,
    ]);
}

it('returns no visitors when sessions are not stored in the database', function (): void {
    config()->set('session.driver', 'array');

    visitorSession('anyone');

    expect(VisitorCounter::current()->onlineNow())->toBe(0);
});

it('counts one visitor per distinct address and agent', function (): void {
    config()->set('session.driver', 'database');

    visitorSession('a-1');
    visitorSession('a-2');
    visitorSession('a-3');
    visitorSession('b', ['ip_address' => '10.0.0.2']);
    visitorSession('c', ['user_agent' => 'Mozilla/5.0 (iPhone) AppleWebKit/605.1 Version/18.0 Safari/604.1']);

    expect(VisitorCounter::current()->onlineNow())->toBe(3);
});

it('ignores sessions outside the window', function (): void {
    config()->set('session.driver', 'database');

    visitorSession('fresh');
    visitorSession('stale', ['ip_address' => '10.0.0.2', 'last_activity' => now()->subHour()->getTimestamp()]);

    expect(VisitorCounter::current()->onlineNow())->toBe(1);
});

it('honours a custom window', function (): void {
    config()->set('session.driver', 'database');

    visitorSession('recent');
    visitorSession('older', ['ip_address' => '10.0.0.2', 'last_activity' => now()->subMinutes(30)->getTimestamp()]);

    expect(VisitorCounter::current()->onlineNow())->toBe(1)
        ->and(VisitorCounter::current()->onlineNow(60))->toBe(2);
});

it('ignores signed-in sessions', function (): void {
    config()->set('session.driver', 'database');

    $user = User::factory()->create();

    visitorSession('guest');
    DB::table('sessions')->insert([
        'id' => 'member',
        'user_id' => $user->id,
        'ip_address' => '10.0.0.2',
        'user_agent' => 'Mozilla/5.0 (Macintosh) Chrome/141.0 Safari/537.36',
        'payload' => '',
        'last_activity' => now()->getTimestamp(),
    ]);

    expect(VisitorCounter::current()->onlineNow())->toBe(1);
});

it('does not count automated clients as visitors', function (): void {
    config()->set('session.driver', 'database');

    visitorSession('human');
    visitorSession('cli', ['ip_address' => '10.0.0.2', 'user_agent' => 'curl/8.7.1']);
    visitorSession('headless', ['ip_address' => '10.0.0.3', 'user_agent' => 'Mozilla/5.0 (Macintosh) HeadlessChrome/151.0.79 Safari/537.36']);
    visitorSession('crawler', ['ip_address' => '10.0.0.4', 'user_agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)']);
    visitorSession('blank', ['ip_address' => '10.0.0.5', 'user_agent' => '']);

    expect(VisitorCounter::current()->onlineNow())->toBe(1);
});

it('treats a missing agent as automated', function (): void {
    expect(VisitorCounter::looksAutomated(null))->toBeTrue()
        ->and(VisitorCounter::looksAutomated(''))->toBeTrue()
        ->and(VisitorCounter::looksAutomated('   '))->toBeTrue();
});

it('flags known automated agents and passes real browsers', function (string $agent, bool $automated): void {
    expect(VisitorCounter::looksAutomated($agent))->toBe($automated);
})->with([
    ['curl/8.7.1', true],
    ['Wget/1.21.4', true],
    ['python-requests/2.32.3', true],
    ['GuzzleHttp/7', true],
    ['Googlebot/2.1', true],
    ['Mozilla/5.0 (compatible; bingbot/2.0)', true],
    ['Mozilla/5.0 AppleWebKit/537.36 HeadlessChrome/151.0.79', true],
    ['facebookexternalhit/1.1', true],
    ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', false],
    ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1', false],
    ['Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:130.0) Gecko/20100101 Firefox/130.0', false],
]);
