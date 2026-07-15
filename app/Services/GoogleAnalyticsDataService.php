<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GoogleAnalyticsDataService
{
    private const string TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const string DATA_BASE = 'https://analyticsdata.googleapis.com/v1beta';

    private const string SCOPE = 'https://www.googleapis.com/auth/analytics.readonly';

    public function configured(): bool
    {
        return filled(config('services.google_analytics.property_id'))
            && filled(config('services.google_analytics.credentials'));
    }

    /**
     * @return array{activeUsers: int, newUsers: int, sessions: int, pageViews: int}
     */
    public function totals(CarbonInterface $start, CarbonInterface $end): array
    {
        $rows = $this->rows($this->runReport([
            'dateRanges' => [$this->dateRange($start, $end)],
            'metrics' => [
                ['name' => 'activeUsers'],
                ['name' => 'newUsers'],
                ['name' => 'sessions'],
                ['name' => 'screenPageViews'],
            ],
        ]));

        $metrics = $rows[0]['metrics'] ?? [];

        return [
            'activeUsers' => $metrics[0] ?? 0,
            'newUsers' => $metrics[1] ?? 0,
            'sessions' => $metrics[2] ?? 0,
            'pageViews' => $metrics[3] ?? 0,
        ];
    }

    /**
     * @return array<int, array{date: string, users: int}>
     */
    public function usersOverTime(CarbonInterface $start, CarbonInterface $end): array
    {
        $rows = $this->rows($this->runReport([
            'dateRanges' => [$this->dateRange($start, $end)],
            'dimensions' => [['name' => 'date']],
            'metrics' => [['name' => 'activeUsers']],
            'orderBys' => [['dimension' => ['dimensionName' => 'date']]],
        ]));

        $byDate = [];

        foreach ($rows as $row) {
            $raw = $row['dimensions'][0] ?? '';

            if (preg_match('/^\d{8}$/', $raw) !== 1) {
                continue;
            }

            $byDate[mb_substr($raw, 0, 4).'-'.mb_substr($raw, 4, 2).'-'.mb_substr($raw, 6, 2)] = $row['metrics'][0] ?? 0;
        }

        $series = [];

        foreach ($start->toPeriod($end) as $day) {
            $date = $day->format('Y-m-d');
            $series[] = ['date' => $date, 'users' => $byDate[$date] ?? 0];
        }

        return $series;
    }

    /**
     * @return array<int, array{country: string, users: int}>
     */
    public function topCountries(CarbonInterface $start, CarbonInterface $end, int $limit = 10): array
    {
        $rows = $this->rows($this->runReport([
            'dateRanges' => [$this->dateRange($start, $end)],
            'dimensions' => [['name' => 'country']],
            'metrics' => [['name' => 'activeUsers']],
            'orderBys' => [['metric' => ['metricName' => 'activeUsers'], 'desc' => true]],
            'limit' => $limit,
        ]));

        return array_map(fn (array $row): array => [
            'country' => $row['dimensions'][0] ?? '',
            'users' => $row['metrics'][0] ?? 0,
        ], $rows);
    }

    /**
     * @return array<int, array{path: string, title: string, views: int}>
     */
    public function topPages(CarbonInterface $start, CarbonInterface $end, int $limit = 10): array
    {
        $rows = $this->rows($this->runReport([
            'dateRanges' => [$this->dateRange($start, $end)],
            'dimensions' => [['name' => 'pagePath'], ['name' => 'pageTitle']],
            'metrics' => [['name' => 'screenPageViews']],
            'orderBys' => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]],
            'limit' => $limit,
        ]));

        return array_map(function (array $row): array {
            $path = $row['dimensions'][0] ?? '';
            $title = $row['dimensions'][1] ?? '';

            return [
                'path' => $path,
                'title' => $title === '' ? $path : $title,
                'views' => $row['metrics'][0] ?? 0,
            ];
        }, $rows);
    }

    /**
     * @return array{startDate: string, endDate: string}
     */
    private function dateRange(CarbonInterface $start, CarbonInterface $end): array
    {
        return ['startDate' => $start->toDateString(), 'endDate' => $end->toDateString()];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function runReport(array $payload): array
    {
        $propertyId = (string) config('services.google_analytics.property_id');

        return Cache::remember(
            'google-analytics:report:'.hash('sha256', $propertyId.json_encode($payload)),
            now()->addMinutes(30),
            fn (): array => Http::retry(2, 100)->withToken($this->accessToken())
                ->post(self::DATA_BASE."/properties/{$propertyId}:runReport", $payload)
                ->throw()
                ->json() ?? [],
        );
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<int, array{dimensions: array<int, string>, metrics: array<int, int>}>
     */
    private function rows(array $response): array
    {
        $raw = $response['rows'] ?? null;

        if (! is_array($raw)) {
            return [];
        }

        $rows = [];

        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }

            $dimensions = [];

            foreach (is_array($row['dimensionValues'] ?? null) ? $row['dimensionValues'] : [] as $value) {
                $dimensions[] = is_array($value) && is_string($value['value'] ?? null) ? $value['value'] : '';
            }

            $metrics = [];

            foreach (is_array($row['metricValues'] ?? null) ? $row['metricValues'] : [] as $value) {
                $metrics[] = is_array($value) && is_numeric($value['value'] ?? null) ? (int) $value['value'] : 0;
            }

            $rows[] = ['dimensions' => $dimensions, 'metrics' => $metrics];
        }

        return $rows;
    }

    private function accessToken(): string
    {
        $credentials = $this->credentials();

        return Cache::remember(
            'google-analytics:token:'.hash('sha256', $credentials['client_email'].$credentials['private_key']),
            now()->addMinutes(55),
            function () use ($credentials): string {
                $header = $this->base64UrlEncode((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
                $claims = $this->base64UrlEncode((string) json_encode([
                    'iss' => $credentials['client_email'],
                    'scope' => self::SCOPE,
                    'aud' => self::TOKEN_URL,
                    'iat' => now()->getTimestamp(),
                    'exp' => now()->addHour()->getTimestamp(),
                ]));

                $privateKey = openssl_pkey_get_private($credentials['private_key']);

                throw_if($privateKey === false, RuntimeException::class, 'The Google Analytics service account private key is invalid.');

                openssl_sign($header.'.'.$claims, $signature, $privateKey, OPENSSL_ALGO_SHA256);

                $token = Http::retry(2, 100)->asForm()
                    ->post(self::TOKEN_URL, [
                        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                        'assertion' => $header.'.'.$claims.'.'.$this->base64UrlEncode($signature),
                    ])
                    ->throw()
                    ->json('access_token');

                return is_string($token) ? $token : '';
            },
        );
    }

    /**
     * @return array{client_email: string, private_key: string}
     */
    private function credentials(): array
    {
        $decoded = json_decode((string) config('services.google_analytics.credentials'), true);
        $email = is_array($decoded) ? ($decoded['client_email'] ?? null) : null;
        $key = is_array($decoded) ? ($decoded['private_key'] ?? null) : null;

        throw_if(! is_string($email) || $email === '' || ! is_string($key) || $key === '', RuntimeException::class, 'The Google Analytics service account credentials are incomplete.');

        return ['client_email' => $email, 'private_key' => $key];
    }

    private function base64UrlEncode(string $value): string
    {
        return mb_rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
