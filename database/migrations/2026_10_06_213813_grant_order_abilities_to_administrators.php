<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $role = DB::table('roles')->where('key', 'admin')->first(['id', 'abilities']);

        if ($role === null) {
            return;
        }

        $abilities = json_decode((string) $role->abilities, true);
        $abilities = is_array($abilities) ? array_values(array_filter($abilities, is_string(...))) : [];

        if (array_any($abilities, fn (string $ability): bool => str_starts_with($ability, 'orders.'))) {
            return;
        }

        DB::table('roles')->where('id', $role->id)->update(['abilities' => json_encode([...$abilities, 'orders.view', 'orders.edit'])]);
    }

    public function down(): void {}
};
