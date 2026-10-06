<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('record_types', function (Blueprint $table): void {
            $table->boolean('sellable')->default(false)->after('has_index_page');
        });

        Schema::table('records', function (Blueprint $table): void {
            $table->unsignedInteger('stock')->nullable()->after('data');
        });
    }

    public function down(): void
    {
        Schema::table('records', function (Blueprint $table): void {
            $table->dropColumn('stock');
        });

        Schema::table('record_types', function (Blueprint $table): void {
            $table->dropColumn('sellable');
        });
    }
};
