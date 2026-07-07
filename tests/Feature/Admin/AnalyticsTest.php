<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

function analyticsServiceAccountKey(): string
{
    static $key = null;

    if ($key === null) {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        assert($resource !== false);
        openssl_pkey_export($resource, $key);
    }

    return $key;
}

function configureAnalyticsReports(): void
{
    config()->set('services.google_analytics.property_id', '123456789');
    config()->set('services.google_analytics.credentials', json_encode([
        'client_email' => 'reports@example.iam.gserviceaccount.com',
        'private_key' => analyticsServiceAccountKey(),
    ]));
}

/**
 * @param  array<int, string>  $dimensions
 * @param  array<int, int>  $metrics
 * @return array<string, mixed>
 */
function analyticsReportRow(array $dimensions, array $metrics): array
{
    return [
        'dimensionValues' => array_map(fn (string $value): array => ['value' => $value], $dimensions),
        'metricValues' => array_map(fn (int $value): array => ['value' => (string) $value], $metrics),
    ];
}

it('returns not found when analytics reports are not configured', function (): void {
    $this->actingAsAdmin()
        ->get(route('admin.analytics'))
        ->assertNotFound();
});

it('redirects guests to the login screen', function (): void {
    $this->fromRoute('home')
        ->get(route('admin.analytics'))
        ->assertRedirectToRoute('login');
});

it('redirects authenticated non-admin users away', function (): void {
    $nonAdmin = User::factory()->create(['active' => true, 'role' => 'member']);

    $this->actingAs($nonAdmin)
        ->fromRoute('home')
        ->get(route('admin.analytics'))
        ->assertRedirectToRoute('home');
});

it('renders the report widgets from the google analytics data', function (): void {
    configureAnalyticsReports();

    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-token']),
        'analyticsdata.googleapis.com/*' => Http::sequence()
            ->push(['rows' => [analyticsReportRow([], [321, 45, 654, 987])]])
            ->push(['rows' => [analyticsReportRow([now()->format('Ymd')], [12])]])
            ->push(['rows' => [analyticsReportRow(['Netherlands'], [42]), analyticsReportRow(['Romania'], [17])]])
            ->push(['rows' => [analyticsReportRow(['/pricing', 'Pricing'], [30])]]),
    ]);

    $this->actingAsAdmin()
        ->get(route('admin.analytics'))
        ->assertOk()
        ->assertSeeLivewire('pages::admin.analytics')
        ->assertSee('321')
        ->assertSee('45')
        ->assertSee('654')
        ->assertSee('987')
        ->assertSee('Netherlands')
        ->assertSee('Romania')
        ->assertSee('Pricing')
        ->assertSee('/pricing');
});

it('shows a friendly warning when google analytics does not respond', function (): void {
    configureAnalyticsReports();

    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_grant'], 401),
        'analyticsdata.googleapis.com/*' => Http::response(['error' => 'forbidden'], 403),
    ]);

    $this->actingAsAdmin()
        ->get(route('admin.analytics'))
        ->assertOk()
        ->assertSee(__('Analytics unavailable'));
});

it('shows the empty state when the period has no data', function (): void {
    configureAnalyticsReports();

    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-token']),
        'analyticsdata.googleapis.com/*' => Http::response(['rows' => []]),
    ]);

    $this->actingAsAdmin()
        ->get(route('admin.analytics'))
        ->assertOk()
        ->assertSee(__('No data for this period.'));
});

it('re-queries the reports when the date range changes', function (): void {
    configureAnalyticsReports();

    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-token']),
        'analyticsdata.googleapis.com/*' => Http::response(['rows' => []]),
    ]);

    $this->actingAsAdmin();

    Livewire::test('pages::admin.analytics')
        ->set('datesFilter.start', '2026-01-01')
        ->set('datesFilter.end', '2026-01-05');

    Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), 'analyticsdata.googleapis.com')
        && $request['dateRanges'] === [['startDate' => '2026-01-01', 'endDate' => '2026-01-05']]);
});

it('shows the analytics sidebar link only when reports are configured', function (): void {
    $this->actingAsAdmin()
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('admin/analytics', false);

    configureAnalyticsReports();

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('admin/analytics', false);
});
