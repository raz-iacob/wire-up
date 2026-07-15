<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\MediaType;
use App\Models\Media;
use finfo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

final readonly class ImportMediaFromUrlAction
{
    private const int MAX_BYTES = 20 * 1024 * 1024;

    /**
     * @var array<string, array{extension: string, type: MediaType}>
     */
    private const array ALLOWED_MIMES = [
        'image/jpeg' => ['extension' => 'jpg', 'type' => MediaType::IMAGE],
        'image/png' => ['extension' => 'png', 'type' => MediaType::IMAGE],
        'image/webp' => ['extension' => 'webp', 'type' => MediaType::IMAGE],
        'image/gif' => ['extension' => 'gif', 'type' => MediaType::IMAGE],
        'application/pdf' => ['extension' => 'pdf', 'type' => MediaType::DOCUMENT],
    ];

    public function __construct(private CreateMediaAction $createMedia) {}

    public function handle(string $url, string $alt = ''): Media
    {
        $this->assertPublicUrl($url);

        $contents = Http::withOptions([
            'allow_redirects' => [
                'max' => 3,
                'on_redirect' => function (RequestInterface $request, ResponseInterface $response, UriInterface $uri): void {
                    $this->assertPublicUrl((string) $uri);
                },
            ],
        ])->timeout(20)->get($url)->throw()->body();

        throw_if($contents === '', InvalidArgumentException::class, 'The URL returned an empty response.');

        throw_if(mb_strlen($contents, '8bit') > self::MAX_BYTES, InvalidArgumentException::class, 'The file is larger than the 20 MB import limit.');

        $mime = (string) new finfo(FILEINFO_MIME_TYPE)->buffer($contents);
        $allowed = self::ALLOWED_MIMES[$mime] ?? null;

        throw_if($allowed === null, InvalidArgumentException::class, "Unsupported file type ({$mime}). Supported types: JPEG, PNG, WebP, GIF, and PDF.");

        $etag = md5($contents);
        $existing = Media::query()->where('etag', $etag)->first();

        if ($existing instanceof Media) {
            return $existing;
        }

        $slug = Str::slug(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME)) ?: 'imported';
        $filename = Str::uuid()->toString().'_'.$slug.'.'.$allowed['extension'];

        Storage::disk(config()->string('filesystems.media'))->put("media/{$filename}", $contents, 'public');

        $dimensions = $allowed['type'] === MediaType::IMAGE ? getimagesizefromstring($contents) : false;

        return $this->createMedia->handle([
            'type' => $allowed['type']->value,
            'source' => "media/{$filename}",
            'etag' => $etag,
            'filename' => $slug.'.'.$allowed['extension'],
            'alt_text' => $alt,
            'mime_type' => $mime,
            'size' => mb_strlen($contents, '8bit'),
            'width' => $dimensions !== false ? $dimensions[0] : null,
            'height' => $dimensions !== false ? $dimensions[1] : null,
            'metadata' => ['source' => 'url', 'origin_url' => $url],
        ]);
    }

    private function assertPublicUrl(string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST);

        throw_if(! is_string($host) || $host === '', InvalidArgumentException::class, 'The URL is not valid.');

        foreach ($this->resolveHost(mb_trim($host, '[]')) as $ip) {
            throw_if(
                filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false,
                InvalidArgumentException::class,
                'For security, only files on public internet addresses can be imported.',
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
