<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;

final class ImageController
{
    public function show(Request $request, string $options, string $path): Response
    {
        if (app()->isProduction() && $this->validIpAddress($request->ip())) {
            $this->ratelimit($request, $path);
        }

        return ImageService::make($path)
            ->applyOptionsString($options)
            ->response();
    }

    private function ratelimit(Request $request, string $path): void
    {
        $allowed = RateLimiter::attempt(
            key: 'img:'.$request->ip().':'.$path,
            maxAttempts: 2,
            callback: fn (): true => true
        );

        abort_if(! $allowed, code: 404, message: 'File not found');
    }

    private function validIpAddress(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
