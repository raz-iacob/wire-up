<?php

declare(strict_types=1);

namespace App\Services;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use RuntimeException;

final class BlockHeading
{
    /**
     * @var list<string>
     */
    public const array LEVELS = ['h1', 'h2', 'h3', 'h4'];

    /**
     * @var list<string>
     */
    private const array AUTHORED_LEVELS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

    /**
     * @var list<string>
     */
    private const array PROMOTED_ELEMENTS = ['p', 'div'];

    public static function level(mixed $value, string $default = 'h2'): string
    {
        if (is_string($value) && in_array($value, self::LEVELS, true)) {
            return $value;
        }

        return in_array($default, self::LEVELS, true) ? $default : 'h2';
    }

    public static function html(string $heading, string $level = 'h2'): string
    {
        $level = self::level($level);

        if (mb_trim($heading) === '') {
            return '';
        }

        if (! str_contains($heading, '<')) {
            return self::wrapped($heading, $level);
        }

        $document = HTMLDocument::createFromString('<div>'.$heading.'</div>', LIBXML_NOERROR);
        $root = $document->getElementsByTagName('div')->item(0);

        throw_unless($root instanceof Element, RuntimeException::class, 'The heading wrapper element went missing.');

        $html = '';
        $promoted = false;

        /** @var list<Node> $run */
        $run = [];

        foreach (iterator_to_array($root->childNodes) as $child) {
            $tag = $child instanceof Element ? mb_strtolower($child->tagName) : '';

            if ($tag === '' || ! in_array($tag, [...self::AUTHORED_LEVELS, ...self::PROMOTED_ELEMENTS], true)) {
                $run[] = $child;

                continue;
            }

            $html .= self::flushed($document, $run, $level, $promoted);
            $run = [];

            $promotable = in_array($tag, self::PROMOTED_ELEMENTS, true)
                && mb_trim($child->innerHTML) !== ''
                && ! $promoted;

            $html .= $promotable
                ? self::promoted($document, $child, $level)
                : $document->saveHtml($child);

            $promoted = $promoted || $promotable || ! in_array($tag, self::PROMOTED_ELEMENTS, true);
        }

        return $html.self::flushed($document, $run, $level, $promoted);
    }

    /**
     * @param  list<Node>  $nodes
     */
    private static function flushed(HTMLDocument $document, array $nodes, string $level, bool &$promoted): string
    {
        $inner = '';

        foreach ($nodes as $node) {
            $inner .= $document->saveHtml($node);
        }

        if (mb_trim($inner) === '') {
            return '';
        }

        if ($promoted) {
            return $inner;
        }

        $promoted = true;

        return self::wrapped($inner, $level);
    }

    private static function wrapped(string $inner, string $level): string
    {
        return mb_trim($inner) === '' ? '' : "<{$level}>{$inner}</{$level}>";
    }

    private static function promoted(HTMLDocument $document, Element $element, string $level): string
    {
        $heading = $document->createElement($level);

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $heading->setAttribute($attribute->nodeName, $attribute->nodeValue ?? '');
        }

        foreach (iterator_to_array($element->childNodes) as $child) {
            $heading->appendChild($child);
        }

        $element->parentNode?->replaceChild($heading, $element);

        return $document->saveHtml($heading);
    }
}
