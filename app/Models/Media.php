<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MediaType;
use App\Services\ImageService;
use Carbon\CarbonImmutable;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property MediaType $type
 * @property string $source
 * @property string $etag
 * @property string|null $filename
 * @property string|null $alt_text
 * @property string|null $mime_type
 * @property string|null $thumbnail
 * @property int|null $size
 * @property int|null $duration
 * @property int|null $width
 * @property int|null $height
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read string $url
 * @property-read string $downloadUrl
 * @property-read string $dimensions
 * @property-read Collection<int, Mediable> $mediables
 * @property-read Mediable $pivot
 */
final class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    /**
     * @return HasMany<Mediable, $this>
     */
    public function mediables(): HasMany
    {
        return $this->hasMany(Mediable::class);
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'type' => MediaType::class,
            'source' => 'string',
            'etag' => 'string',
            'filename' => 'string',
            'alt_text' => 'string',
            'mime_type' => 'string',
            'thumbnail' => 'string',
            'size' => 'integer',
            'duration' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function delete(): bool
    {
        if ($this->canDeleteSafely()) {
            parent::delete();

            Storage::disk(config('filesystems.media'))->delete($this->source);
            if ($this->thumbnail) {
                Storage::disk(config('filesystems.media'))->delete($this->thumbnail);
            }

            return true;
        }

        return false;
    }

    /**
     * @return Attribute<?string, null>
     */
    protected function dimensions(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->type === MediaType::IMAGE ? $this->width.' x '.$this->height : null
        );
    }

    /**
     * @return Attribute<string, null>
     */
    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn () => Storage::temporaryUrl($this->source, now()->addMinutes(15))
        );
    }

    /**
     * @return Attribute<string, null>
     */
    protected function downloadUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->type !== MediaType::VIDEO ? Storage::temporaryUrl($this->source, now()->addHours(3), [
                'ResponseContentDisposition' => 'attachment; filename="'.addslashes($this->filename ?? 'noname').'"',
                'ResponseContentType' => $this->mime_type,
            ]) : null
        );
    }

    /**
     * @return Attribute<string, null>
     */
    protected function preview(): Attribute
    {
        return Attribute::make(
            get: fn (): string => match ($this->type) {
                MediaType::IMAGE => route('image.show', ['w=350,h=200', $this->source]),
                default => $this->thumbnail ? route('image.show', ['w=350,h=200', $this->thumbnail]) : ImageService::placeholder(),
            }
        );
    }

    private function canDeleteSafely(): bool
    {
        return $this->mediables()
            ->where('media_id', $this->id)->doesntExist();
    }
}
