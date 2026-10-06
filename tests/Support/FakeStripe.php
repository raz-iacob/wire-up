<?php

declare(strict_types=1);

namespace Tests\Support;

use Closure;
use Illuminate\Support\Str;
use PHPUnit\Framework\Assert;
use RuntimeException;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;

final class FakeStripe implements ClientInterface
{
    /**
     * @var array<int, array{method: string, path: string, params: array<string, mixed>}>
     */
    private array $requests = [];

    /**
     * @var array<int, array{pattern: string, body: array<string, mixed>|Closure(array<string, mixed>): array<string, mixed>, status: int}>
     */
    private array $responses = [];

    public static function install(): self
    {
        $fake = new self;

        ApiRequestor::setHttpClient($fake);

        return $fake;
    }

    /**
     * @param  array<string, mixed>|Closure(array<string, mixed>): array<string, mixed>  $body
     */
    public function respond(string $method, string $path, array|Closure $body, int $status = 200): self
    {
        array_unshift($this->responses, ['pattern' => mb_strtoupper($method).' '.$path, 'body' => $body, 'status' => $status]);

        return $this;
    }

    public function fail(string $method, string $path, string $message, int $status = 400): self
    {
        return $this->respond($method, $path, ['error' => ['message' => $message, 'type' => 'invalid_request_error']], $status);
    }

    /**
     * @param  'delete'|'get'|'post'  $method
     * @param  string  $absUrl
     * @param  array<int, string>  $headers
     * @param  array<string, mixed>  $params
     * @param  bool  $hasFile
     * @param  'v1'|'v2'  $apiMode
     * @param  int|null  $maxNetworkRetries
     * @return array{0: string, 1: int, 2: array<string, string>}
     */
    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null): array
    {
        $request = mb_strtoupper($method).' '.parse_url($absUrl, PHP_URL_PATH);

        $this->requests[] = ['method' => mb_strtoupper($method), 'path' => (string) parse_url($absUrl, PHP_URL_PATH), 'params' => $params];

        foreach ($this->responses as $response) {
            if (Str::is($response['pattern'], $request)) {
                $body = $response['body'] instanceof Closure ? ($response['body'])($params) : $response['body'];

                return [(string) json_encode($body), $response['status'], []];
            }
        }

        throw new RuntimeException('Unexpected Stripe request: '.$request);
    }

    /**
     * @param  (Closure(array<string, mixed>): bool)|null  $callback
     */
    public function assertSent(string $method, string $path, ?Closure $callback = null): void
    {
        Assert::assertNotEmpty(
            $this->matching($method, $path, $callback),
            'Expected Stripe request ['.mb_strtoupper($method).' '.$path.'] was not sent.',
        );
    }

    public function assertNotSent(string $method, string $path): void
    {
        Assert::assertEmpty(
            $this->matching($method, $path),
            'Unexpected Stripe request ['.mb_strtoupper($method).' '.$path.'] was sent.',
        );
    }

    public function assertNothingSent(): void
    {
        Assert::assertSame([], $this->requests, 'Stripe requests were sent unexpectedly.');
    }

    /**
     * @param  (Closure(array<string, mixed>): bool)|null  $callback
     * @return array<int, array{method: string, path: string, params: array<string, mixed>}>
     */
    private function matching(string $method, string $path, ?Closure $callback = null): array
    {
        return array_values(array_filter(
            $this->requests,
            fn (array $request): bool => $request['method'] === mb_strtoupper($method)
                && Str::is($path, $request['path'])
                && (! $callback instanceof Closure || $callback($request['params'])),
        ));
    }
}
