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
        $type = $this->faker->randomElement(MediaType::cases());

        return [
            'type' => $type,
            'source' => $this->generateSource($type),
            'etag' => $this->faker->md5(),
            'filename' => $this->faker->word().'.'.$this->getExtension($type),
            'alt_text' => $this->faker->sentence(),
            'mime_type' => $this->getMimeType($type),
            'thumbnail' => $type === MediaType::VIDEO
                ? 'thumbnails/'.$this->faker->uuid().'.jpg'
                : null,
            'size' => $this->faker->numberBetween(1024, 10485760),
            'duration' => in_array($type, [MediaType::AUDIO, MediaType::VIDEO], true)
                ? $this->faker->numberBetween(30, 7200)
                : null,
            'width' => $type === MediaType::DOCUMENT ? null : $this->faker->numberBetween(100, 4000),
            'height' => $type === MediaType::DOCUMENT ? null : $this->faker->numberBetween(100, 3000),
        ];
    }

    public function externalVideo(): static
    {
        return $this->state(fn (): array => [
            'type' => MediaType::VIDEO,
            'source' => $this->faker->randomElement([
                'https://www.youtube.com/watch?v='.$this->faker->regexify('[A-Za-z0-9_-]{11}'),
                'https://vimeo.com/'.$this->faker->numberBetween(100000, 999999999),
            ]),
            'filename' => null,
            'mime_type' => null,
            'etag' => null,
        ]);
    }

    private function generateSource(MediaType $type): string
    {
        return match ($type) {
            MediaType::IMAGE => 'images/'.$this->faker->uuid().'.jpg',
            MediaType::VIDEO => $this->faker->boolean()
                ? 'videos/'.$this->faker->uuid().'.mp4'
                : 'https://www.youtube.com/watch?v='.$this->faker->regexify('[A-Za-z0-9_-]{11}'),
            MediaType::DOCUMENT => 'documents/'.$this->faker->uuid().'.pdf',
            MediaType::AUDIO => 'audio/'.$this->faker->uuid().'.mp3',
        };
    }

    private function getExtension(MediaType $type): string
    {
        return match ($type) {
            MediaType::IMAGE => $this->faker->randomElement(['jpg', 'jpeg', 'png', 'webp']),
            MediaType::VIDEO => $this->faker->randomElement(['mp4', 'mov', 'avi']),
            MediaType::DOCUMENT => $this->faker->randomElement(['pdf', 'doc', 'docx']),
            MediaType::AUDIO => $this->faker->randomElement(['mp3', 'wav', 'ogg']),
        };
    }

    private function getMimeType(MediaType $type): string
    {
        return match ($type) {
            MediaType::IMAGE => $this->faker->randomElement(['image/jpeg', 'image/png', 'image/webp']),
            MediaType::VIDEO => $this->faker->randomElement(['video/mp4', 'video/quicktime', 'video/avi']),
            MediaType::DOCUMENT => $this->faker->randomElement(['application/pdf', 'application/msword']),
            MediaType::AUDIO => $this->faker->randomElement(['audio/mpeg', 'audio/wav', 'audio/ogg']),
        };
    }
}
