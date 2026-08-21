<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class VisitorCounter
{
    /**
     * @var array<int, string>
     */
    private const array AUTOMATED_AGENTS = [
        'bot', 'crawler', 'spider', 'slurp', 'headless', 'monitor', 'uptime',
        'preview', 'fetcher', 'scraper', 'archiver', 'validator', 'lighthouse',
        'curl', 'wget', 'httpie', 'python', 'java/', 'go-http-client', 'okhttp',
        'axios', 'guzzle', 'libwww', 'phantomjs', 'puppeteer', 'playwright',
        'facebookexternalhit', 'embedly', 'whatsapp', 'telegram',
    ];

    public static function current(): self
    {
        return new self;
    }

    public static function looksAutomated(?string $userAgent): bool
    {
        $agent = mb_strtolower(mb_trim((string) $userAgent));

        if ($agent === '') {
            return true;
        }

        return array_any(self::AUTOMATED_AGENTS, fn (string $needle): bool => str_contains($agent, $needle));
    }

    public function onlineNow(int $minutes = 15): int
    {
        if (config()->string('session.driver') !== 'database') {
            return 0;
        }

        return DB::table(config()->string('session.table', 'sessions'))
            ->select('ip_address', 'user_agent')
            ->whereNull('user_id')
            ->where('last_activity', '>=', now()->subMinutes($minutes)->getTimestamp())
            ->distinct()
            ->get()
            ->reject(fn (object $row): bool => self::looksAutomated(
                is_string($row->user_agent) ? $row->user_agent : null
            ))
            ->count();
    }
}
