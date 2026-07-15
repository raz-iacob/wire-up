<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class PublicUrlGuard
{
    public function assertPublic(string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST);

        throw_if(! is_string($host) || $host === '', InvalidArgumentException::class, 'The URL is not valid.');

        foreach ($this->resolveHost(mb_trim($host, '[]')) as $ip) {
            throw_if(
                filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false,
                InvalidArgumentException::class,
                'For security, only public internet addresses can be fetched.',
            );
        }
    }

    /**
     * @return array<int, string>
     */
    private function resolveHost(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $ipv4 = gethostbynamel($host) ?: [];
        $ipv6 = array_column(dns_get_record($host, DNS_AAAA) ?: [], 'ipv6');

        return array_values(array_filter([...$ipv4, ...$ipv6]));
    }
}
