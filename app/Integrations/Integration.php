<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Enums\SettingKind;
use Illuminate\Support\Str;

abstract class Integration
{
    /**
     * @return array<string, SettingKind>
     */
    abstract public function settings(): array;

    /**
     * @return list<string>
     */
    abstract public function configKeys(): array;

    /**
     * @param  array<string, mixed>  $saved
     * @return array<string, mixed>
     */
    abstract public function config(array $saved): array;

    abstract public function hint(string $setting, string $saved): string;

    /**
     * @return list<string>
     */
    final public function secrets(): array
    {
        return array_keys(array_filter($this->settings(), fn (SettingKind $kind): bool => $kind === SettingKind::SECRET));
    }

    final protected function lastFour(string $saved): string
    {
        return $saved === '' ? '' : '…'.Str::substr($saved, -4);
    }

    /**
     * @param  array<string, mixed>  $saved
     */
    final protected function text(array $saved, string $key): string
    {
        $value = $saved[$key] ?? null;

        return is_string($value) ? $value : '';
    }
}
