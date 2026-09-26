<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MediaType;
use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
 */
final class MediaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(MediaType::cases()),
            'source' => fn (array $attributes): string => $this->generateSource($this->typeOf($attributes)),
            'etag' => fake()->md5(),
            'filename' => fn (array $attributes): string => fake()->word().'.'.$this->getExtension($this->typeOf($attributes)),
            'alt_text' => fake()->sentence(),
            'mime_type' => fn (array $attributes): string => $this->getMimeType($this->typeOf($attributes)),
            'thumbnail' => fn (array $attributes): ?string => $this->typeOf($attributes) === MediaType::VIDEO
                ? 'thumbnails/'.fake()->uuid().'.jpg'
                : null,
            'size' => fake()->numberBetween(1024, 10485760),
            'duration' => fn (array $attributes): ?int => in_array($this->typeOf($attributes), [MediaType::AUDIO, MediaType::VIDEO], true)
                ? fake()->numberBetween(30, 7200)
                : null,
            'width' => fn (array $attributes): ?int => $this->typeOf($attributes) === MediaType::DOCUMENT ? null : fake()->numberBetween(100, 4000),
            'height' => fn (array $attributes): ?int => $this->typeOf($attributes) === MediaType::DOCUMENT ? null : fake()->numberBetween(100, 3000),
        ];
    }

    public function pexels(): static
    {
        $pexelsId = fake()->numberBetween(1000, 9999999);
        $photographer = fake()->name();

        return $this->state(fn (): array => [
            'type' => MediaType::IMAGE,
            'mime_type' => 'image/jpeg',
            'metadata' => [
                'source' => 'pexels',
                'pexels_id' => $pexelsId,
                'photographer' => $photographer,
                'photographer_url' => 'https://www.pexels.com/@'.fake()->userName(),
                'pexels_url' => 'https://www.pexels.com/photo/'.$pexelsId,
            ],
        ]);
    }

    public function externalVideo(): static
    {
        return $this->state(fn (): array => [
            'type' => MediaType::VIDEO,
            'source' => fake()->randomElement([
                'https://www.youtube.com/watch?v='.fake()->regexify('[A-Za-z0-9_-]{11}'),
                'https://vimeo.com/'.fake()->numberBetween(100000, 999999999),
            ]),
            'filename' => null,
            'mime_type' => null,
            'etag' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function typeOf(array $attributes): MediaType
    {
        $type = $attributes['type'] ?? null;

        return $type instanceof MediaType ? $type : MediaType::tryFrom((string) $type) ?? MediaType::IMAGE;
    }

    private function generateSource(MediaType $type): string
    {
        return match ($type) {
            MediaType::IMAGE => 'images/'.fake()->uuid().'.jpg',
            MediaType::VIDEO => 'videos/'.fake()->uuid().'.mp4',
            MediaType::DOCUMENT => 'documents/'.fake()->uuid().'.pdf',
            MediaType::AUDIO => 'audio/'.fake()->uuid().'.mp3',
        };
    }

    private function getExtension(MediaType $type): string
    {
        return match ($type) {
            MediaType::IMAGE => fake()->randomElement(['jpg', 'jpeg', 'png', 'webp']),
            MediaType::VIDEO => fake()->randomElement(['mp4', 'mov', 'avi']),
            MediaType::DOCUMENT => fake()->randomElement(['pdf', 'doc', 'docx']),
            MediaType::AUDIO => fake()->randomElement(['mp3', 'wav', 'ogg']),
        };
    }

    private function getMimeType(MediaType $type): string
    {
        return match ($type) {
            MediaType::IMAGE => fake()->randomElement(['image/jpeg', 'image/png', 'image/webp']),
            MediaType::VIDEO => fake()->randomElement(['video/mp4', 'video/quicktime', 'video/avi']),
            MediaType::DOCUMENT => fake()->randomElement(['application/pdf', 'application/msword']),
            MediaType::AUDIO => fake()->randomElement(['audio/mpeg', 'audio/wav', 'audio/ogg']),
        };
    }
}
