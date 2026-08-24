<?php

declare(strict_types=1);

namespace App\Services;

use Dom\Element;
use Dom\HTMLDocument;
use RuntimeException;

final class BlockHtmlSanitizer
{
    /**
     * @var list<string>
     */
    private const array FORBIDDEN_ELEMENTS = [
        'script', 'style', 'iframe', 'object', 'embed', 'form',
        'link', 'meta', 'base', 'applet', 'frame', 'frameset', 'noscript',
    ];

    /**
     * @var array<string, list<string>>
     */
    private const array VERBATIM_FIELDS = [
        'code' => ['code'],
    ];

    /**
     * @var list<string>
     */
    private const array URL_ATTRIBUTES = ['href', 'src', 'action', 'formaction', 'poster', 'background'];

    /**
     * @var list<string>
     */
    private const array DANGEROUS_MARKERS = [
        '<script', '<style', '<iframe', '<object', '<embed', '<form',
        '<link', '<meta', '<base', '<applet', '<frame', '<noscript',
        'javascript:', 'vbscript:', 'data:text/html',
    ];

    /**
     * @param  array<array-key, mixed>  $content
     * @return array<array-key, mixed>
     */
    public static function forBlock(string $type, array $content): array
    {
        $verbatim = self::VERBATIM_FIELDS[$type] ?? [];
        $sanitized = [];

        foreach ($content as $key => $value) {
            $sanitized[$key] = in_array($key, $verbatim, true) ? $value : self::sanitized($value);
        }

        return $sanitized;
    }

    /**
     * @param  array<array-key, mixed>  $content
     * @return array<array-key, mixed>
     */
    public static function content(array $content): array
    {
        $sanitized = [];

        foreach ($content as $key => $value) {
            $sanitized[$key] = self::sanitized($value);
        }

        return $sanitized;
    }

    public static function value(string $value): string
    {
        if (self::isDangerousUrl($value)) {
            return '';
        }

        if (! self::mayCarryDangerousMarkup($value)) {
            return $value;
        }

        return self::withoutDangerousMarkup($value);
    }

    private static function sanitized(mixed $value): mixed
    {
        return match (true) {
            is_array($value) => self::content($value),
            is_string($value) => self::value($value),
            default => $value,
        };
    }

    private static function isDangerousUrl(string $value): bool
    {
        if (preg_match('/^\s*(?:javascript|vbscript|data)\s*:/i', $value) !== 1) {
            return false;
        }

        return ! str_starts_with(mb_strtolower(mb_trim($value)), 'data:image/');
    }

    private static function mayCarryDangerousMarkup(string $value): bool
    {
        $lowered = mb_strtolower($value);

        foreach (self::DANGEROUS_MARKERS as $marker) {
            if (str_contains($lowered, $marker)) {
                return true;
            }
        }

        return preg_match('/\son[a-z]+\s*=/i', $value) === 1;
    }

    private static function withoutDangerousMarkup(string $value): string
    {
        $document = HTMLDocument::createFromString('<div>'.$value.'</div>', LIBXML_NOERROR);

        $root = $document->getElementsByTagName('div')->item(0);

        throw_unless($root instanceof Element, RuntimeException::class, 'The sanitizer wrapper element went missing.');

        foreach (self::FORBIDDEN_ELEMENTS as $tag) {
            foreach (iterator_to_array($root->getElementsByTagName($tag)) as $element) {
                $element->remove();
            }
        }

        foreach (iterator_to_array($root->getElementsByTagName('*')) as $element) {
            self::stripDangerousAttributes($element);
        }

        $html = '';

        foreach ($root->childNodes as $child) {
            $html .= $document->saveHtml($child);
        }

        return $html;
    }

    private static function stripDangerousAttributes(Element $element): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = mb_strtolower($attribute->nodeName);

            if (str_starts_with($name, 'on')) {
                $element->removeAttribute($attribute->nodeName);

                continue;
            }

            if (in_array($name, self::URL_ATTRIBUTES, true) && self::isDangerousUrl($attribute->nodeValue ?? '')) {
                $element->removeAttribute($attribute->nodeName);
            }
        }
    }
}
