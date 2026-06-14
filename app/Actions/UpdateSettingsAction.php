<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Settings;

final readonly class UpdateSettingsAction
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function handle(array $values): void
    {
        Settings::set($values);
    }
}
