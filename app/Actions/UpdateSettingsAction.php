<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Settings;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final readonly class UpdateSettingsAction
{
    /**
     * @var array<int, string>
     */
    private const array MEDIA_ROLES = ['favicon', 'logo', 'logo_dark', 'logo_icon'];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Settings $settings, array $attributes): void
    {
        DB::transaction(function () use ($settings, $attributes): void {

            $settings->fill(Arr::only($attributes, ['title', 'description']));

            if (array_key_exists('metadata', $attributes)) {
                $settings->metadata = [...$settings->metadata ?? [], ...$attributes['metadata']];
            }

            $settings->save();

            foreach (self::MEDIA_ROLES as $role) {
                if (array_key_exists($role, $attributes)) {
                    $this->syncMedia($settings, $role, $attributes[$role]);
                }
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
