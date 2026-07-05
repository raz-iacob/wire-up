<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use App\Services\RolePresets;
use Illuminate\Database\Seeder;

final class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (RolePresets::all() as $preset) {
            Role::query()->updateOrCreate(
                ['key' => $preset['key']],
                [
                    'name' => $preset['name'],
                    'abilities' => $preset['abilities'],
                    'bypass' => $preset['bypass'],
                    'is_protected' => $preset['is_protected'],
                ],
            );
        }
    }
}
