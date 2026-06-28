<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BlockType;
use App\Models\Block;

final readonly class BlockSchema
{
    /**
     * @var array<string, string>
     */
    private const array CURRENCY_SYMBOLS = ['$' => 'USD', '€' => 'EUR', '£' => 'GBP', '¥' => 'JPY'];

    public function __construct(
        private string $pageName,
        private string $orgId,
        private ?string $fallbackImage,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function nodes(Block $block, string $locale): array
    {
        return match ($block->type) {
            BlockType::LOCATION => $this->location($block, $locale),
            BlockType::TEAM => $this->team($block, $locale),
            BlockType::PRICING => $this->pricing($block, $locale),
            BlockType::VIDEO => $this->video($block, $locale),
            BlockType::AUDIO => $this->audio($block, $locale),
            BlockType::GALLERY => $this->gallery($block),
            default => [],
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function location(Block $block, string $locale): array
    {
        $content = $block->content ?? [];
        $address = $this->clean($block->text('address', $locale));
        $phone = mb_trim((string) ($content['phone'] ?? ''));
        $email = mb_trim((string) ($content['email'] ?? ''));

        if ($address === '' && $phone === '' && $email === '') {
            return [];
        }

        $node = [
            '@type' => 'LocalBusiness',
            'name' => $this->clean($block->text('name', $locale)) ?: $this->pageName,
        ];

        if ($address !== '') {
            $node['address'] = $address;
        }
        if ($phone !== '') {
            $node['telephone'] = $phone;
        }
        if ($email !== '') {
            $node['email'] = $email;
        }

        $map = mb_trim((string) ($content['map'] ?? ''));
        if (str_starts_with($map, 'http')) {
            $node['hasMap'] = $map;
        }

        return [$node];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function team(Block $block, string $locale): array
    {
        $nodes = [];

        foreach ($this->items($block) as $i => $item) {
            $name = $this->clean($block->text("items.{$i}.name", $locale));

            if ($name === '') {
                continue;
            }

            $person = ['@type' => 'Person', 'name' => $name, 'worksFor' => ['@id' => $this->orgId]];

            $role = $this->clean($block->text("items.{$i}.role", $locale));
            if ($role !== '') {
                $person['jobTitle'] = $role;
            }

            $bio = $this->clean($block->text("items.{$i}.bio", $locale));
            if ($bio !== '') {
                $person['description'] = $bio;
            }

            $photo = $block->imageUrl("items.{$i}.photo", ['w' => 400, 'h' => 400]);
            if ($photo !== null) {
                $person['image'] = $photo;
            }

            $socials = is_array($item['socials'] ?? null) ? $item['socials'] : [];

            $email = mb_trim((string) ($socials['email'] ?? ''));
            if ($email !== '') {
                $person['email'] = $email;
            }

            $sameAs = [];
            foreach (['website', 'linkedin', 'x', 'instagram'] as $key) {
                $url = mb_trim((string) ($socials[$key] ?? ''));
                if ($url !== '') {
                    $sameAs[] = $url;
                }
            }
            if ($sameAs !== []) {
                $person['sameAs'] = $sameAs;
            }

            $nodes[] = $person;
        }

        return $nodes;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pricing(Block $block, string $locale): array
    {
        $nodes = [];

        foreach (array_keys($this->items($block)) as $i) {
            $name = $this->clean($block->text("items.{$i}.name", $locale));

            if ($name === '') {
                continue;
            }

            $offer = ['@type' => 'Offer', 'name' => $name];

            $description = $this->clean($block->text("items.{$i}.description", $locale));
            if ($description !== '') {
                $offer['description'] = $description;
            }

            $price = $this->parsePrice($block->text("items.{$i}.price", $locale));
            if ($price !== null) {
                $offer['price'] = $price['price'];
                $offer['priceCurrency'] = $price['currency'];
            }

            $nodes[] = $offer;
        }

        return $nodes;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function video(Block $block, string $locale): array
    {
        $embed = $block->videoEmbed();

        if ($embed === null) {
            return [];
        }

        $thumbnail = $block->imageUrl('poster', ['w' => 1280]) ?? $block->posterUrl('video', ['w' => 1280]) ?? $this->fallbackImage;

        if ($thumbnail === null) {
            return [];
        }

        $node = [
            '@type' => 'VideoObject',
            'name' => $this->clean($block->text('heading', $locale)) ?: $this->pageName,
            'thumbnailUrl' => $thumbnail,
            'uploadDate' => $block->created_at->toAtomString(),
        ];

        $description = $this->clean($block->text('intro', $locale));
        if ($description !== '') {
            $node['description'] = $description;
        }

        if ($embed['kind'] === 'native') {
            $node['contentUrl'] = $embed['src'];
        } else {
            $node['embedUrl'] = $embed['provider'] === 'youtube'
                ? 'https://www.youtube.com/embed/'.$embed['id']
                : 'https://player.vimeo.com/video/'.$embed['id'];
        }

        return [$node];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function audio(Block $block, string $locale): array
    {
        $src = $block->fileUrl('audio');

        if ($src === null) {
            return [];
        }

        $node = [
            '@type' => 'AudioObject',
            'name' => $this->clean($block->text('heading', $locale)) ?: $this->pageName,
            'contentUrl' => $src,
        ];

        $description = $this->clean($block->text('intro', $locale));
        if ($description !== '') {
            $node['description'] = $description;
        }

        return [$node];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function gallery(Block $block): array
    {
        $nodes = [];

        foreach ($this->items($block, 'media') as $i => $item) {
            if ($block->isVideo("media.{$i}")) {
                continue;
            }

            $url = $block->imageUrl("media.{$i}", ['w' => 1600]);
            if ($url === null) {
                continue;
            }

            $node = ['@type' => 'ImageObject', 'contentUrl' => $url];

            $alt = $block->imageAlt("media.{$i}");
            if ($alt !== '') {
                $node['name'] = $alt;
            }

            $caption = mb_trim((string) data_get($item, 'metadata.caption', ''));
            if ($caption !== '') {
                $node['caption'] = $caption;
            }

            $nodes[] = $node;
        }

        return $nodes;
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
     * @return array{price: string, currency: string}|null
     */
    private function parsePrice(string $raw): ?array
    {
        $currency = array_find(self::CURRENCY_SYMBOLS, fn (string $code, string $symbol): bool => str_contains($raw, $symbol));
        if ($currency === null) {
            return null;
        }

        $normalized = (string) preg_replace('/,(?=\d{3}(?:\D|$))/', '', $raw);

        if (preg_match('/\d+(?:\.\d+)?/', $normalized, $matches) !== 1) {
            return null;
        }

        return ['price' => $matches[0], 'currency' => $currency];
    }

    private function clean(string $value): string
    {
        $spaced = (string) preg_replace('/<\/(?:p|div|li|h[1-6])>|<br\s*\/?>/i', ' ', $value);
        $decoded = html_entity_decode(strip_tags($spaced), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return (string) str(str_replace("\u{00A0}", ' ', $decoded))->squish();
    }
}
