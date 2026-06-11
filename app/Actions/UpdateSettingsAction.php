<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Settings;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final readonly class UpdateSettingsAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Settings $settings, array $attributes): void
    {
        DB::transaction(function () use ($settings, $attributes): void {

            $settings->fill(Arr::only($attributes, ['title', 'description']))->save();

            if (array_key_exists('favicon', $attributes)) {
                $this->syncMedia($settings, 'favicon', $attributes['favicon']);
            }

        });
    }

    /**
     * @param  array<string, mixed>|null  $item
     */
    private function syncMedia(Settings $settings, string $role, ?array $item): void
    {
        $items = isset($item['id']) ? [$item] : [];

        $settings->syncMediaForRole($role, resolve('localization')->getDefaultLocale(), $items);
    }
}
