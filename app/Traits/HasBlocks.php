<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Block;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

trait HasBlocks
{
    /**
     * @return MorphMany<Block, $this>
     */
    public function blocks(): MorphMany
    {
        return $this->morphMany(Block::class, 'blockable')->orderBy('position');
    }

    /**
     * @return array<int|string, array{id: string, type: string, content: array<string, mixed>}>
     */
    public function getBlocksArray(): array
    {
        return $this->blocks
            ->mapWithKeys(fn (Block $block): array => [
                (string) $block->id => [
                    'id' => (string) $block->id,
                    'type' => $block->type->value,
                    'content' => $block->content ?? [],
                ],
            ])
            ->all();
    }

    /**
     * @param  array<int|string, array{id?: string, type: string, content?: array<string, mixed>}>  $blocks
     */
    public function updateBlocks(array $blocks): void
    {
        $keptIds = collect($blocks)
            ->pluck('id')
            ->filter(fn (mixed $id): bool => is_numeric($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $this->blocks()->whereNotIn('id', $keptIds)->delete();

        foreach (array_values($blocks) as $position => $block) {
            $attributes = [
                'type' => $block['type'],
                'position' => $position,
                'content' => $block['content'] ?? [],
            ];

            if (! isset($block['id']) || Str::startsWith($block['id'], 'new-')) {
                $this->blocks()->create($attributes);

                continue;
            }

            $this->blocks()->whereKey($block['id'])->first()?->update($attributes);
        }
    }

    protected static function bootHasBlocks(): void
    {
        static::deleting(function (self $model): void {
            $model->blocks()->delete();
        });
    }
}
