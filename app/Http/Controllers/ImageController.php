<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ImageService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ImageController
{
    public function show(Request $request, string $options, string $path): Response|BinaryFileResponse
    {
        abort_unless($request->hasValidRelativeSignature() || (bool) auth()->user()?->canAccessAdmin(), 404, 'File not found');

        if (str_ends_with(mb_strtolower($path), '.svg')) {
            $response = ImageService::svg($path);
        } elseif (! ($response = ImageService::cached($options, $path)) instanceof BinaryFileResponse) {
            $this->ratelimitTransforms($request);

            $response = ImageService::transform($options, $path);
        }

        if (SettingsService::current()->noindex()) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }

    private function ratelimitTransforms(Request $request): void
    {
        if (! app()->isProduction() || ! $this->validIpAddress((string) $request->ip())) {
            return;
        }

        $allowed = RateLimiter::attempt(
            key: 'img-transform:'.$request->ip(),
            maxAttempts: 30,
            callback: fn (): true => true
        );

        abort_if(! $allowed, code: 429, message: 'Too many requests');
    }

    private function validIpAddress(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
