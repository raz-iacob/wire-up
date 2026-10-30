<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $developerId = DB::table('roles')->where('key', 'developer')->value('id') ?? DB::table('roles')->insertGetId([
            'key' => 'developer',
            'name' => 'Developer',
            'abilities' => json_encode([]),
            'bypass' => true,
            'is_protected' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (DB::table('users')->where('role_id', $developerId)->exists()) {
            return;
        }

        $firstOwnerId = DB::table('users')
            ->whereIn('role_id', DB::table('roles')->where('key', 'owner')->select('id'))
            ->orderBy('id')
            ->value('id');

        if ($firstOwnerId !== null) {
            DB::table('users')->where('id', $firstOwnerId)->update(['role_id' => $developerId]);
        }
    }
};
