<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MediaType;
use App\Models\Media;
use App\Models\Record;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

final readonly class RecordSchema
{
    public function __construct(
        private string $orgId,
        private ?string $fallbackImage,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function nodes(Record $record, string $url, string $description): array
    {
        $node = match ($record->recordType->key) {
            'product' => $this->product($record, $url),
            'post' => $this->article($record, $url),
            'event' => $this->event($record),
            'team-member' => $this->person($record),
            'project' => $this->creativeWork($record),
            'service' => $this->service(),
            'job' => $this->jobPosting($record),
            default => null,
        };

        if ($node === null) {
            return [];
        }

        $node['@id'] = $url.'#'.$record->recordType->key;
        $node['name'] ??= $record->title;

        if ($description !== '' && ! isset($node['description'])) {
            $node['description'] = $description;
        }

        $images = $this->images($record);
        if ($images !== []) {
            $node['image'] = $images;
        }

        return [$node];
    }

    /**
     * @return array<string, mixed>
     */
    private function product(Record $record, string $url): array
    {
        $node = ['@type' => 'Product'];

        $sku = $this->value($record, 'sku');
        if (is_string($sku) && $sku !== '') {
            $node['sku'] = $sku;
        }

        $price = $this->number($this->value($record, 'current_price'))
            ?? $this->number($this->value($record, 'regular_price'));

        if ($price !== null) {
            $node['offers'] = [
                '@type' => 'Offer',
                'price' => $price,
                'priceCurrency' => SettingsService::current()->currency(),
                'availability' => 'https://schema.org/InStock',
                'url' => $url,
            ];
        }

        return $node;
    }

    /**
     * @return array<string, mixed>
     */
    private function article(Record $record, string $url): array
    {
        $node = [
            '@type' => 'Article',
            'headline' => $record->title,
            'mainEntityOfPage' => $url,
            'dateModified' => $record->updated_at->toAtomString(),
            'author' => ['@id' => $this->orgId],
            'publisher' => ['@id' => $this->orgId],
        ];

        $published = $this->publishedAt($record);
        if ($published !== null) {
            $node['datePublished'] = $published;
        }

        return $node;
    }

    /**
     * @return array<string, mixed>
     */
    private function event(Record $record): array
    {
        $node = ['@type' => 'Event', 'organizer' => ['@id' => $this->orgId]];

        $start = $this->date($this->value($record, 'starts_at'));
        if ($start !== null) {
            $node['startDate'] = $start;
        }

        $end = $this->date($this->value($record, 'ends_at'));
        if ($end !== null) {
            $node['endDate'] = $end;
        }

        $location = $this->value($record, 'location');
        if (is_string($location) && $location !== '') {
            $node['location'] = ['@type' => 'Place', 'name' => $location];
        }

        return $node;
    }

    /**
     * @return array<string, mixed>
     */
    private function person(Record $record): array
    {
        $node = ['@type' => 'Person', 'worksFor' => ['@id' => $this->orgId]];

        $role = $this->value($record, 'role');
        if (is_string($role) && $role !== '') {
            $node['jobTitle'] = $role;
        }

        return $node;
    }

    /**
     * @return array<string, mixed>
     */
    private function creativeWork(Record $record): array
    {
        $node = ['@type' => 'CreativeWork', 'creator' => ['@id' => $this->orgId]];

        $link = $this->value($record, 'link');
        if (is_string($link) && str_starts_with($link, 'http')) {
            $node['url'] = $link;
        }

        return $node;
    }

    /**
     * @return array<string, mixed>
     */
    private function service(): array
    {
        return ['@type' => 'Service', 'provider' => ['@id' => $this->orgId]];
    }

    /**
     * @return array<string, mixed>
     */
    private function jobPosting(Record $record): array
    {
        $node = [
            '@type' => 'JobPosting',
            'title' => $record->title,
            'hiringOrganization' => ['@id' => $this->orgId],
        ];

        $posted = $this->publishedAt($record);
        if ($posted !== null) {
            $node['datePosted'] = $posted;
        }

        $employment = $this->value($record, 'employment_type');
        if (is_string($employment) && $employment !== '') {
            $node['employmentType'] = $employment;
        }

        $location = $this->value($record, 'location');
        if (is_string($location) && $location !== '') {
            $node['jobLocation'] = ['@type' => 'Place', 'address' => $location];
        }

        return $node;
    }

    /**
     * @return array<int, string>
     */
    private function images(Record $record): array
    {
        $urls = [];

        foreach ([...$record->fieldMedia('photo', false), ...$record->fieldMedia('gallery', false)] as $media) {
            if ($media->type === MediaType::VIDEO) {
                continue;
            }

            $urls[] = $this->mediaUrl($media, 1600);
        }

        if ($urls === [] && $this->fallbackImage !== null) {
            $urls[] = $this->fallbackImage;
        }

        return $urls;
    }

    private function mediaUrl(Media $media, int $width): string
    {
        $cropSet = is_array($media->pivot->crop ?? null) ? $media->pivot->crop : [];
        $crop = is_array($cropSet['default'] ?? null) ? $cropSet['default'] : [];
        $options = ["w={$width}"];

        if (($crop['crop_w'] ?? 0) > 0 && ($crop['crop_h'] ?? 0) > 0) {
            $options[] = sprintf('crop=%d-%d-%d-%d', $crop['crop_w'], $crop['crop_h'], $crop['crop_x'] ?? 0, $crop['crop_y'] ?? 0);
        }

        return ImageService::url(implode(',', $options), $media->source);
    }

    private function value(Record $record, string $key): mixed
    {
        $field = collect($record->recordType->fields)->firstWhere('key', $key);

        if (! is_array($field)) {
            return null;
        }

        return $record->fieldValue($key, (bool) ($field['translatable'] ?? false));
    }

    private function number(mixed $value): ?string
    {
        return is_numeric($value) ? (string) (float) $value : null;
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return rescue(fn (): string => Date::parse($value)->toAtomString(), null, false);
    }

    private function publishedAt(Record $record): ?string
    {
        return $record->published_at instanceof CarbonInterface ? $record->published_at->toAtomString() : null;
    }
}
