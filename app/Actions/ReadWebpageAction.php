<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\PublicUrlGuard;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Throwable;

final readonly class ReadWebpageAction
{
    private const int MAX_CRAWL = 20;

    private const int CONTENT_LIMIT = 6000;

    private const int IMAGE_LIMIT = 40;

    /**
     * @var array<int, string>
     */
    private const array ASSET_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'bmp', 'avif',
        'pdf', 'zip', 'rar', 'gz', 'dmg', 'exe', 'css', 'js', 'json', 'xml',
        'mp4', 'webm', 'mov', 'mp3', 'wav', 'woff', 'woff2', 'ttf', 'eot',
    ];

    public function __construct(private PublicUrlGuard $urlGuard) {}

    /**
     * @return array{start_url: string, page_count: int, pages: array<int, array<string, mixed>>}
     */
    public function handle(string $url, int $maxPages = 8): array
    {
        $maxPages = max(1, min($maxPages, self::MAX_CRAWL));
        $this->urlGuard->assertPublic($url);

        $baseHost = (string) parse_url($url, PHP_URL_HOST);
        $queue = [$this->canonical($url)];
        $visited = [];
        $pages = [];

        while ($queue !== [] && count($pages) < $maxPages) {
            $current = array_shift($queue);
            $visited[$current] = true;

            $html = $this->fetch($current);

            if ($html === null) {
                continue;
            }

            [$page, $links] = $this->parse($current, $html, $baseHost);
            $pages[] = $page;

            foreach ($links as $link) {
                if (! isset($visited[$link]) && ! in_array($link, $queue, true)) {
                    $queue[] = $link;
                }
            }
        }

        return ['start_url' => $url, 'page_count' => count($pages), 'pages' => $pages];
    }

    private function fetch(string $url): ?string
    {
        try {
            $this->urlGuard->assertPublic($url);

            $response = Http::withOptions([
                'allow_redirects' => [
                    'max' => 3,
                    'on_redirect' => function (RequestInterface $request, ResponseInterface $response, UriInterface $uri): void {
                        $this->urlGuard->assertPublic((string) $uri);
                    },
                ],
            ])->timeout(20)->get($url);

            if ($response->failed() || ! Str::contains((string) $response->header('Content-Type'), 'html', true)) {
                return null;
            }

            return $response->body();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    private function parse(string $url, string $html, string $baseHost): array
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();

        $xpath = new DOMXPath($document);

        $page = [
            'url' => $url,
            'title' => $this->title($xpath),
            'description' => $this->metaDescription($xpath),
            'content' => $this->content($xpath),
            'images' => $this->images($xpath, $url),
            'nav_links' => [],
        ];

        $internal = [];

        foreach ($this->elements($xpath->query('//a[@href]')) as $anchor) {
            $href = $this->absolutize($url, $anchor->getAttribute('href'));

            if ($href === null) {
                continue;
            }

            if ($this->withinChrome($anchor)) {
                $text = $this->clean($anchor->textContent);

                if ($text !== '') {
                    $page['nav_links'][] = ['text' => $text, 'url' => $href];
                }
            }

            $clean = (string) strtok($href, '#');

            if (parse_url($clean, PHP_URL_HOST) === $baseHost && ! $this->isAsset($clean)) {
                $internal[$clean] = true;
            }
        }

        return [$page, array_keys($internal)];
    }

    private function title(DOMXPath $xpath): string
    {
        $title = $this->clean($this->firstText($xpath, '//title'));

        return $title !== '' ? $title : $this->clean($this->firstText($xpath, '//body//h1'));
    }

    private function metaDescription(DOMXPath $xpath): string
    {
        $meta = $xpath->query('//meta[@name="description"]/@content');

        return $meta && $meta->item(0) !== null ? $this->clean((string) $meta->item(0)->nodeValue) : '';
    }

    private function content(DOMXPath $xpath): string
    {
        $lines = [];

        foreach ($this->elements($xpath->query('//body//h1 | //body//h2 | //body//h3 | //body//h4 | //body//p | //body//li | //body//blockquote')) as $element) {
            if ($this->withinChrome($element)) {
                continue;
            }

            $text = $this->clean($element->textContent);

            if ($text === '') {
                continue;
            }

            $lines[] = match ($element->nodeName) {
                'h1' => '# '.$text,
                'h2' => '## '.$text,
                'h3' => '### '.$text,
                'h4' => '#### '.$text,
                'li' => '- '.$text,
                'blockquote' => '> '.$text,
                default => $text,
            };
        }

        return mb_substr(implode("\n\n", $lines), 0, self::CONTENT_LIMIT);
    }

    /**
     * @return array<int, array{src: string, alt: string}>
     */
    private function images(DOMXPath $xpath, string $base): array
    {
        $images = [];

        foreach ($this->elements($xpath->query('//img[@src]')) as $img) {
            $src = $this->absolutize($base, $img->getAttribute('src'));

            if ($src !== null && ! isset($images[$src])) {
                $images[$src] = ['src' => $src, 'alt' => $this->clean($img->getAttribute('alt'))];
            }
        }

        return array_slice(array_values($images), 0, self::IMAGE_LIMIT);
    }

    private function firstText(DOMXPath $xpath, string $query): string
    {
        $node = ($xpath->query($query) ?: null)?->item(0);

        return $node instanceof DOMNode ? $node->textContent : '';
    }

    /**
     * @param  DOMNodeList<DOMNode>|false  $nodes
     * @return array<int, DOMElement>
     */
    private function elements(DOMNodeList|false $nodes): array
    {
        return array_values(array_filter(
            [...($nodes ?: [])],
            fn (DOMNode $node): bool => $node instanceof DOMElement,
        ));
    }

    private function withinChrome(DOMElement $element): bool
    {
        for ($node = $element->parentNode; $node instanceof DOMNode; $node = $node->parentNode) {
            if (in_array($node->nodeName, ['nav', 'header', 'footer'], true)) {
                return true;
            }
        }

        return false;
    }

    private function isAsset(string $url): bool
    {
        $extension = mb_strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        return in_array($extension, self::ASSET_EXTENSIONS, true);
    }

    private function clean(string $text): string
    {
        return mb_trim((string) preg_replace('/\s+/', ' ', $text));
    }

    private function canonical(string $url): string
    {
        return (string) strtok($url, '#');
    }

    private function absolutize(string $base, string $href): ?string
    {
        $href = mb_trim($href);

        if ($href === '' || Str::startsWith($href, ['#', 'mailto:', 'tel:', 'javascript:', 'data:'])) {
            return null;
        }

        if (preg_match('#^https?://#i', $href) === 1) {
            return $href;
        }

        $scheme = (string) parse_url($base, PHP_URL_SCHEME);

        if (Str::startsWith($href, '//')) {
            return $scheme.':'.$href;
        }

        $port = parse_url($base, PHP_URL_PORT);
        $origin = $scheme.'://'.parse_url($base, PHP_URL_HOST).($port !== null ? ':'.$port : '');

        if (Str::startsWith($href, '/')) {
            return $origin.$this->normalizePath($href);
        }

        $directory = (string) preg_replace('#/[^/]*$#', '/', (string) (parse_url($base, PHP_URL_PATH) ?? '/'));

        return $origin.$this->normalizePath($directory.$href);
    }

    private function normalizePath(string $path): string
    {
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '') {
                continue;
            }
            if ($segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return '/'.implode('/', $segments);
    }
}
