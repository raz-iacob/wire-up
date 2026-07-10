<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BlockType;
use App\Models\Block;
use League\HTMLToMarkdown\HtmlConverter;

final readonly class BlockMarkdown
{
    private HtmlConverter $converter;

    public function __construct()
    {
        $this->converter = new HtmlConverter([
            'strip_tags' => true,
            'remove_nodes' => 'script style',
            'header_style' => 'atx',
        ]);
    }

    public function render(Block $block, string $locale): string
    {
        return match ($block->type) {
            BlockType::HERO => $this->hero($block, $locale),
            BlockType::TEXT_IMAGE => $this->textImage($block, $locale),
            BlockType::LOCATION => $this->location($block, $locale),
            BlockType::ACCORDION => $this->accordion($block, $locale),
            BlockType::GALLERY => $this->gallery($block),
            BlockType::VIDEO => $this->video($block, $locale),
            BlockType::PHOTO => $this->photo($block, $locale),
            BlockType::TESTIMONIALS => $this->testimonials($block, $locale),
            BlockType::SPONSORS => $this->sponsors($block, $locale),
            BlockType::FEATURE_CARDS => $this->featureCards($block, $locale),
            BlockType::COLLECTION => $this->collection($block, $locale),
            BlockType::SEARCH => $this->heading($block, 'heading', $locale),
            BlockType::BUTTONS => $this->buttons($block, $locale),
            BlockType::AUDIO => $this->audio($block, $locale),
            BlockType::DOWNLOADS => $this->downloads($block, $locale),
            BlockType::RICH_TEXT => $this->richText($block, $locale),
            BlockType::STATS => $this->stats($block, $locale),
            BlockType::TEAM => $this->team($block, $locale),
            BlockType::PRICING => $this->pricing($block, $locale),
            BlockType::CONTACT_FORM => $this->contactForm($block, $locale),
            BlockType::SPACER => '',
            BlockType::DIVIDER => '---',
        };
    }

    public function fromHtml(string $html): string
    {
        if (mb_trim(strip_tags($html)) === '') {
            return '';
        }

        return mb_trim($this->converter->convert($html));
    }

    private function hero(Block $block, string $locale): string
    {
        return $this->join([
            $this->heading($block, 'heading', $locale),
            $this->inline($block->text('subheading', $locale)),
            $this->ctas($block, ['ctaPrimary', 'ctaSecondary'], $locale),
        ]);
    }

    private function textImage(Block $block, string $locale): string
    {
        return $this->join([
            $this->heading($block, 'heading', $locale),
            $this->inline($block->text('subheading', $locale)),
            $this->prose($block, 'body', $locale),
            $this->image($block, 'image'),
            $this->ctas($block, ['ctaPrimary', 'ctaSecondary'], $locale),
        ]);
    }

    private function location(Block $block, string $locale): string
    {
        $content = $block->content ?? [];
        $details = [];

        $address = $this->inline($block->text('address', $locale));
        if ($address !== '') {
            $details[] = $address;
        }

        $phone = mb_trim((string) ($content['phone'] ?? ''));
        if ($phone !== '') {
            $details[] = '**'.__('Phone').':** '.$phone;
        }

        $email = mb_trim((string) ($content['email'] ?? ''));
        if ($email !== '') {
            $details[] = '**'.__('Email').':** '.$email;
        }

        $map = mb_trim((string) ($content['map'] ?? ''));
        if (str_starts_with($map, 'http')) {
            $details[] = '['.__('Map').']('.$map.')';
        }

        return $this->join([
            $this->heading($block, 'name', $locale),
            implode("\n", $details),
        ]);
    }

    private function accordion(Block $block, string $locale): string
    {
        $parts = [];

        foreach (array_keys($this->items($block)) as $i) {
            $parts[] = $this->join([
                $this->heading($block, "items.{$i}.title", $locale, '###'),
                $this->prose($block, "items.{$i}.body", $locale),
            ]);
        }

        return $this->join($parts);
    }

    private function gallery(Block $block): string
    {
        $parts = [];

        foreach (array_keys($this->items($block, 'media')) as $i) {
            if ($block->isVideo("media.{$i}")) {
                continue;
            }

            $parts[] = $this->image($block, "media.{$i}");
        }

        return $this->join($parts);
    }

    private function video(Block $block, string $locale): string
    {
        $link = '';
        $embed = $block->videoEmbed();

        if ($embed !== null) {
            $url = match (true) {
                $embed['kind'] === 'native' => $embed['src'],
                $embed['provider'] === 'youtube' => 'https://www.youtube.com/watch?v='.$embed['id'],
                default => 'https://vimeo.com/'.$embed['id'],
            };

            $link = '['.__('Watch video').']('.$url.')';
        }

        return $this->join([
            $this->heading($block, 'heading', $locale),
            $this->prose($block, 'intro', $locale),
            $link,
        ]);
    }

    private function photo(Block $block, string $locale): string
    {
        $image = $this->image($block, 'image');
        $url = $block->ctaUrl('imageLink');

        if ($image !== '' && $url !== null) {
            $image = '['.$image.']('.$url.')';
        }

        return $this->join([
            $this->heading($block, 'heading', $locale),
            $this->prose($block, 'intro', $locale),
            $image,
        ]);
    }

    private function testimonials(Block $block, string $locale): string
    {
        $parts = [
            $this->heading($block, 'heading', $locale),
            $this->prose($block, 'intro', $locale),
        ];

        foreach (array_keys($this->items($block)) as $i) {
            $quote = $this->inline($block->text("items.{$i}.quote", $locale));

            if ($quote === '') {
                continue;
            }

            $attribution = implode(', ', array_filter([
                $this->inline($block->text("items.{$i}.author", $locale)),
                $this->inline($block->text("items.{$i}.role", $locale)),
            ], fn (string $value): bool => $value !== ''));

            $parts[] = '> '.$quote.($attribution !== '' ? "\n>\n> — ".$attribution : '');
        }

        return $this->join($parts);
    }

    private function sponsors(Block $block, string $locale): string
    {
        $lines = [];

        foreach ($this->items($block) as $i => $item) {
            $name = $this->inline($block->text("items.{$i}.name", $locale));

            if ($name === '') {
                continue;
            }

            $link = mb_trim((string) ($item['link'] ?? ''));
            $lines[] = $link !== '' ? "- [{$name}]({$link})" : "- {$name}";
        }

        return $this->join([
            $this->heading($block, 'heading', $locale),
            $this->prose($block, 'intro', $locale),
            implode("\n", $lines),
        ]);
    }

    private function featureCards(Block $block, string $locale): string
    {
        $parts = [
            $this->heading($block, 'heading', $locale),
            $this->prose($block, 'intro', $locale),
        ];

        foreach (array_keys($this->items($block)) as $i) {
            $parts[] = $this->join([
                $this->heading($block, "items.{$i}.title", $locale, '###'),
                $this->prose($block, "items.{$i}.body", $locale),
                $this->cta($block, "items.{$i}.cta", $locale),
            ]);
        }

        return $this->join($parts);
    }

    private function collection(Block $block, string $locale): string
    {
        $lines = [];

        foreach (resolve(RecordCollectionQuery::class)->resolve($block->content ?? []) as $record) {
            if ($record->getSlug($locale) === '') {
                continue;
            }

            $excerpt = $record->displayExcerpt();
            $lines[] = '- ['.$record->displayHeading().']('.$record->getUrl($locale).')'.($excerpt !== '' ? ': '.$excerpt : '');
        }

        return $this->join([
            $this->heading($block, 'heading', $locale),
            implode("\n", $lines),
            $this->cta($block, 'button', $locale),
        ]);
    }

    private function buttons(Block $block, string $locale): string
    {
        $lines = [];

        foreach (array_keys($this->items($block)) as $i) {
            $text = $this->inline($block->text("items.{$i}.text", $locale));
            $url = $block->ctaUrl("items.{$i}");
            if ($text === '') {
                continue;
            }
            if ($url === null) {
                continue;
            }

            $lines[] = "- [{$text}]({$url})";
        }

        return implode("\n", $lines);
    }

    private function audio(Block $block, string $locale): string
    {
        $src = $block->fileUrl('audio');

        return $this->join([
            $this->heading($block, 'heading', $locale),
            $this->prose($block, 'intro', $locale),
            $src !== null ? '['.__('Listen').']('.$src.')' : '',
        ]);
    }

    private function downloads(Block $block, string $locale): string
    {
        $lines = [];

        foreach ($this->items($block, 'files') as $i => $file) {
            $url = $block->fileUrl("files.{$i}");

            if ($url === null) {
                continue;
            }

            $name = $this->inline((string) (data_get($file, 'metadata.caption') ?: data_get($file, 'filename', __('Download'))));
            $lines[] = "- [{$name}]({$url})";
        }

        return $this->join([
            $this->heading($block, 'heading', $locale),
            $this->prose($block, 'intro', $locale),
            implode("\n", $lines),
        ]);
    }

    private function richText(Block $block, string $locale): string
    {
        return $this->join([
            $this->heading($block, 'heading', $locale),
            $this->prose($block, 'body', $locale),
        ]);
    }

    private function stats(Block $block, string $locale): string
    {
        $lines = [];

        foreach (array_keys($this->items($block)) as $i) {
            $value = $this->inline($block->text("items.{$i}.value", $locale));
            $label = $this->inline($block->text("items.{$i}.label", $locale));

            if ($value === '' && $label === '') {
                continue;
            }

            $lines[] = mb_trim('- '.($value !== '' ? '**'.$value.'**' : '').' '.$label);
        }

        return $this->join([
            $this->heading($block, 'heading', $locale),
            $this->prose($block, 'intro', $locale),
            implode("\n", $lines),
        ]);
    }

    private function team(Block $block, string $locale): string
    {
        $parts = [
            $this->heading($block, 'heading', $locale),
            $this->prose($block, 'intro', $locale),
        ];

        foreach ($this->items($block) as $i => $item) {
            $name = $this->inline($block->text("items.{$i}.name", $locale));

            if ($name === '') {
                continue;
            }

            $socials = is_array($item['socials'] ?? null) ? $item['socials'] : [];
            $links = [];

            $email = mb_trim((string) ($socials['email'] ?? ''));
            if ($email !== '') {
                $links[] = $email;
            }

            foreach (['website' => __('Website'), 'linkedin' => 'LinkedIn', 'x' => 'X', 'instagram' => 'Instagram'] as $key => $label) {
                $url = mb_trim((string) ($socials[$key] ?? ''));

                if ($url !== '') {
                    $links[] = "[{$label}]({$url})";
                }
            }

            $role = $this->inline($block->text("items.{$i}.role", $locale));

            $parts[] = $this->join([
                '### '.$name,
                $role !== '' ? '*'.$role.'*' : '',
                $this->prose($block, "items.{$i}.bio", $locale),
                implode(' · ', $links),
            ]);
        }

        return $this->join($parts);
    }

    private function pricing(Block $block, string $locale): string
    {
        $parts = [
            $this->heading($block, 'heading', $locale),
            $this->prose($block, 'intro', $locale),
        ];

        foreach (array_keys($this->items($block)) as $i) {
            $name = $this->inline($block->text("items.{$i}.name", $locale));

            if ($name === '') {
                continue;
            }

            $price = $this->inline($block->text("items.{$i}.price", $locale));
            $period = $this->inline($block->text("items.{$i}.period", $locale));

            $parts[] = $this->join([
                '### '.$name,
                $price !== '' ? mb_trim('**'.$price.'** '.$period) : '',
                $this->prose($block, "items.{$i}.description", $locale),
                $this->prose($block, "items.{$i}.features", $locale),
                $this->cta($block, "items.{$i}.cta", $locale),
            ]);
        }

        return $this->join($parts);
    }

    private function contactForm(Block $block, string $locale): string
    {
        return $this->join([
            $this->heading($block, 'heading', $locale),
            $this->prose($block, 'description', $locale),
        ]);
    }

    private function heading(Block $block, string $field, string $locale, string $level = '##'): string
    {
        $text = $this->inline($block->text($field, $locale));

        return $text !== '' ? "{$level} {$text}" : '';
    }

    private function prose(Block $block, string $field, string $locale): string
    {
        return $this->fromHtml($block->text($field, $locale));
    }

    private function image(Block $block, string $field): string
    {
        $url = $block->imageUrl($field, ['w' => 1600]);

        if ($url === null) {
            return '';
        }

        return '!['.$this->inline($block->imageAlt($field)).']('.$url.')';
    }

    private function cta(Block $block, string $field, string $locale): string
    {
        if (! (bool) data_get($block->content, "{$field}.enabled", false)) {
            return '';
        }

        $text = $this->inline($block->text("{$field}.text", $locale));
        $url = $block->ctaUrl($field);

        if ($text === '' || $url === null) {
            return '';
        }

        return "[{$text}]({$url})";
    }

    /**
     * @param  array<int, string>  $fields
     */
    private function ctas(Block $block, array $fields, string $locale): string
    {
        $links = array_filter(
            array_map(fn (string $field): string => $this->cta($block, $field, $locale), $fields),
            fn (string $link): bool => $link !== '',
        );

        return implode(' · ', $links);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function items(Block $block, string $key = 'items'): array
    {
        $items = $block->content[$key] ?? null;

        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, is_array(...)));
    }

    /**
     * @param  array<int, string>  $parts
     */
    private function join(array $parts): string
    {
        return implode("\n\n", array_values(array_filter($parts, fn (string $part): bool => $part !== '')));
    }

    private function inline(string $html): string
    {
        $spaced = (string) preg_replace('/<\/(?:p|div|li|h[1-6])>|<br\s*\/?>/i', ' ', $html);
        $decoded = html_entity_decode(strip_tags($spaced), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return (string) str(str_replace("\u{00A0}", ' ', $decoded))->squish();
    }
}
