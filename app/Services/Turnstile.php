<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final readonly class Turnstile
{
    public const string VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function enabled(): bool
    {
        return $this->siteKey() !== '' && $this->secretKey() !== '';
    }

    public function siteKey(): string
    {
        $key = config('site.turnstile_site_key');

        return is_string($key) ? $key : '';
    }

    public function verify(string $token, ?string $ip): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        if ($token === '') {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(8)->post(self::VERIFY_URL, [
                'secret' => $this->secretKey(),
                'response' => $token,
                'remoteip' => $ip,
            ]);
        } catch (ConnectionException $exception) {
            Log::warning('Cloudflare Turnstile could not be reached, so the form was let through.', ['error' => $exception->getMessage()]);

            return true;
        }

        if ($response->serverError()) {
            Log::warning('Cloudflare Turnstile answered with a server error, so the form was let through.', ['status' => $response->status()]);

            return true;
        }

        return $response->json('success') === true;
    }

    private function secretKey(): string
    {
        $key = config('site.turnstile_secret_key');

        return is_string($key) ? $key : '';
    }
}
