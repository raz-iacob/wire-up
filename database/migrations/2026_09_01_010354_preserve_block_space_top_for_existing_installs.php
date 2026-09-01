<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settled = DB::table('settings')->where('key', '!=', 'home_page_id')->exists();

        if (! $settled || DB::table('settings')->where('key', 'block_space_top')->exists()) {
            return;
        }

        DB::table('settings')->insert([
            'key' => 'block_space_top',
            'value' => json_encode(false),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'block_space_top')->delete();
    }
};
