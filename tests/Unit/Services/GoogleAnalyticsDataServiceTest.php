<?php

declare(strict_types=1);

use App\Services\GoogleAnalyticsDataService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;

function googleAnalyticsTestKey(): string
{
    static $key = null;

    if ($key === null) {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        assert($resource !== false);
        openssl_pkey_export($resource, $key);
    }

    return $key;
}

/**
 * @param  array<int, string>  $dimensions
 * @param  array<int, int|string>  $metrics
 * @return array<string, mixed>
 */
function googleAnalyticsRow(array $dimensions, array $metrics): array
{
    return [
        'dimensionValues' => array_map(fn (string $value): array => ['value' => $value], $dimensions),
        'metricValues' => array_map(fn (int|string $value): array => ['value' => (string) $value], $metrics),
    ];
}

beforeEach(function (): void {
    config()->set('services.google_analytics.property_id', '123456789');
    config()->set('services.google_analytics.credentials', json_encode([
        'client_email' => 'reports@example.iam.gserviceaccount.com',
        'private_key' => googleAnalyticsTestKey(),
    ]));
});

it('reports configured only when both the property id and credentials are present', function (): void {
    expect(resolve(GoogleAnalyticsDataService::class)->configured())->toBeTrue();

    config()->set('services.google_analytics.property_id');
    expect(resolve(GoogleAnalyticsDataService::class)->configured())->toBeFalse();

    config()->set('services.google_analytics.property_id', '123456789');
    config()->set('services.google_analytics.credentials');
    expect(resolve(GoogleAnalyticsDataService::class)->configured())->toBeFalse();

    config()->set('services.google_analytics.credentials');
    config()->set('services.google_analytics.property_id');
    expect(resolve(GoogleAnalyticsDataService::class)->configured())->toBeFalse();
});

it('exchanges a signed jwt assertion for an access token', function (): void {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3599]),
        'analyticsdata.googleapis.com/*' => Http::response(['rows' => []]),
    ]);

    resolve(GoogleAnalyticsDataService::class)->totals(Date::parse('2026-06-01'), Date::parse('2026-06-30'));

    $recorded = Http::recorded(fn ($request): bool => str_contains((string) $request->url(), 'oauth2.googleapis.com'));

    expect($recorded)->toHaveCount(1);

    $request = $recorded->first()[0];

    expect($request['grant_type'])->toBe('urn:ietf:params:oauth:grant-type:jwt-bearer');

    [$header, $claims] = explode('.', (string) $request['assertion']);

    $decodedHeader = json_decode(base64_decode(strtr($header, '-_', '+/')), true);
    $decodedClaims = json_decode(base64_decode(strtr($claims, '-_', '+/')), true);

    expect($decodedHeader)->toBe(['alg' => 'RS256', 'typ' => 'JWT'])
        ->and($decodedClaims['iss'])->toBe('reports@example.iam.gserviceaccount.com')
        ->and($decodedClaims['scope'])->toBe('https://www.googleapis.com/auth/analytics.readonly')
        ->and($decodedClaims['aud'])->toBe('https://oauth2.googleapis.com/token')
        ->and($decodedClaims['exp'])->toBe($decodedClaims['iat'] + 3600);
});

it('caches the access token across reports', function (): void {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-token']),
        'analyticsdata.googleapis.com/*' => Http::response(['rows' => []]),
    ]);

    $service = resolve(GoogleAnalyticsDataService::class);
    $service->totals(Date::parse('2026-06-01'), Date::parse('2026-06-30'));
    $service->topCountries(Date::parse('2026-06-01'), Date::parse('2026-06-30'));

    expect(Http::recorded(fn ($request): bool => str_contains((string) $request->url(), 'oauth2.googleapis.com')))->toHaveCount(1)
        ->and(Http::recorded(fn ($request): bool => str_contains((string) $request->url(), 'analyticsdata.googleapis.com')))->toHaveCount(2);
});

it('sends the bearer token to the property run report endpoint', function (): void {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-token']),
        'analyticsdata.googleapis.com/*' => Http::response(['rows' => []]),
    ]);

    resolve(GoogleAnalyticsDataService::class)->totals(Date::parse('2026-06-01'), Date::parse('2026-06-30'));

    Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), '/v1beta/properties/123456789:runReport')
        && $request->hasHeader('Authorization', 'Bearer fake-token'));
});

it('falls back to an empty bearer token when the token response is malformed', function (): void {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['expires_in' => 3599]),
        'analyticsdata.googleapis.com/*' => Http::response(['rows' => []]),
    ]);

    resolve(GoogleAnalyticsDataService::class)->totals(Date::parse('2026-06-01'), Date::parse('2026-06-30'));

    Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), 'analyticsdata.googleapis.com')
        && $request->hasHeader('Authorization', 'Bearer'));
});

it('requests the totals metrics for the period and normalizes them', function (): void {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-token']),
        'analyticsdata.googleapis.com/*' => Http::response([
            'rows' => [googleAnalyticsRow([], [321, 123, 456, 789])],
        ]),
    ]);

    $totals = resolve(GoogleAnalyticsDataService::class)->totals(Date::parse('2026-06-01'), Date::parse('2026-06-30'));

    expect($totals)->toBe(['activeUsers' => 321, 'newUsers' => 123, 'sessions' => 456, 'pageViews' => 789]);

    Http::assertSent(function (Request $request): bool {
        if (! str_contains((string) $request->url(), 'analyticsdata.googleapis.com')) {
            return false;
        }

        return $request['dateRanges'] === [['startDate' => '2026-06-01', 'endDate' => '2026-06-30']]
            && $request['metrics'] === [
                ['name' => 'activeUsers'],
                ['name' => 'newUsers'],
                ['name' => 'sessions'],
                ['name' => 'screenPageViews'],
            ];
    });
});

it('returns zeroed totals when the report has no usable rows', function (array $body): void {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-token']),
        'analyticsdata.googleapis.com/*' => Http::response($body),
    ]);

    $totals = resolve(GoogleAnalyticsDataService::class)->totals(Date::parse('2026-06-01'), Date::parse('2026-06-30'));

    expect($totals)->toBe(['activeUsers' => 0, 'newUsers' => 0, 'sessions' => 0, 'pageViews' => 0]);
})->with([
    'no rows key' => [['kind' => 'analyticsData#runReport']],
    'rows not a list of arrays' => [['rows' => ['bogus']]],
    'row without values' => [['rows' => [['dimensionValues' => 'bogus', 'metricValues' => 'bogus']]]],
]);

it('normalizes the users over time series and zero-fills missing days', function (): void {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-token']),
        'analyticsdata.googleapis.com/*' => Http::response([
            'rows' => [
                googleAnalyticsRow(['20260601'], [5]),
                googleAnalyticsRow(['(other)'], [99]),
                googleAnalyticsRow(['20260603'], [7]),
            ],
        ]),
    ]);

    $series = resolve(GoogleAnalyticsDataService::class)->usersOverTime(Date::parse('2026-06-01'), Date::parse('2026-06-03'));

    expect($series)->toBe([
        ['date' => '2026-06-01', 'users' => 5],
        ['date' => '2026-06-02', 'users' => 0],
        ['date' => '2026-06-03', 'users' => 7],
    ]);

    Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), 'analyticsdata.googleapis.com')
        && $request['dimensions'] === [['name' => 'date']]
        && $request['orderBys'] === [['dimension' => ['dimensionName' => 'date']]]);
});

it('requests the top countries ordered by active users', function (): void {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-token']),
        'analyticsdata.googleapis.com/*' => Http::response([
            'rows' => [
                googleAnalyticsRow(['Netherlands'], [42]),
                googleAnalyticsRow(['Romania'], [17]),
            ],
        ]),
    ]);

    $countries = resolve(GoogleAnalyticsDataService::class)->topCountries(Date::parse('2026-06-01'), Date::parse('2026-06-30'), 5);

    expect($countries)->toBe([
        ['country' => 'Netherlands', 'users' => 42],
        ['country' => 'Romania', 'users' => 17],
    ]);

    Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), 'analyticsdata.googleapis.com')
        && $request['dimensions'] === [['name' => 'country']]
        && $request['orderBys'] === [['metric' => ['metricName' => 'activeUsers'], 'desc' => true]]
        && $request['limit'] === 5);
});

it('requests the top pages and falls back to the path when the title is empty', function (): void {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-token']),
        'analyticsdata.googleapis.com/*' => Http::response([
            'rows' => [
                googleAnalyticsRow(['/', 'Home'], [120]),
                googleAnalyticsRow(['/pricing', ''], [30]),
                ['dimensionValues' => [['value' => 7], ['value' => null]], 'metricValues' => [['value' => 'not-a-number']]],
            ],
        ]),
    ]);

    $pages = resolve(GoogleAnalyticsDataService::class)->topPages(Date::parse('2026-06-01'), Date::parse('2026-06-30'));

    expect($pages)->toBe([
        ['path' => '/', 'title' => 'Home', 'views' => 120],
        ['path' => '/pricing', 'title' => '/pricing', 'views' => 30],
        ['path' => '', 'title' => '', 'views' => 0],
    ]);

    Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), 'analyticsdata.googleapis.com')
        && $request['dimensions'] === [['name' => 'pagePath'], ['name' => 'pageTitle']]
        && $request['orderBys'] === [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]]
        && $request['limit'] === 10);
});

it('caches identical report requests', function (): void {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-token']),
        'analyticsdata.googleapis.com/*' => Http::response(['rows' => []]),
    ]);

    $service = resolve(GoogleAnalyticsDataService::class);
    $service->totals(Date::parse('2026-06-01'), Date::parse('2026-06-30'));
    $service->totals(Date::parse('2026-06-01'), Date::parse('2026-06-30'));

    expect(Http::recorded(fn ($request): bool => str_contains((string) $request->url(), 'analyticsdata.googleapis.com')))->toHaveCount(1);
});

it('rejects incomplete service account credentials', function (?string $credentials): void {
    config()->set('services.google_analytics.credentials', $credentials);

    resolve(GoogleAnalyticsDataService::class)->totals(Date::parse('2026-06-01'), Date::parse('2026-06-30'));
})->throws(RuntimeException::class, 'incomplete')->with([
    'malformed json' => ['not-json'],
    'missing client email' => [json_encode(['private_key' => 'key'])],
    'missing private key' => [json_encode(['client_email' => 'svc@example.com'])],
    'empty client email' => [json_encode(['client_email' => '', 'private_key' => 'key'])],
]);

it('rejects an invalid private key', function (): void {
    config()->set('services.google_analytics.credentials', json_encode([
        'client_email' => 'svc@example.com',
        'private_key' => 'not-a-pem-key',
    ]));

    resolve(GoogleAnalyticsDataService::class)->totals(Date::parse('2026-06-01'), Date::parse('2026-06-30'));
})->throws(RuntimeException::class, 'private key is invalid');

it('propagates token endpoint failures', function (): void {
    Http::fake(['oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_grant'], 401)]);

    resolve(GoogleAnalyticsDataService::class)->totals(Date::parse('2026-06-01'), Date::parse('2026-06-30'));
})->throws(RequestException::class);

it('propagates report endpoint failures', function (): void {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-token']),
        'analyticsdata.googleapis.com/*' => Http::response(['error' => 'forbidden'], 403),
    ]);

    resolve(GoogleAnalyticsDataService::class)->totals(Date::parse('2026-06-01'), Date::parse('2026-06-30'));
})->throws(RequestException::class);
