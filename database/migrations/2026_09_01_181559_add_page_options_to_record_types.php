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
            $table->boolean('has_detail_page')->default(true)->after('breadcrumbs');
            $table->boolean('has_index_page')->default(false)->after('has_detail_page');
        });
    }

    public function down(): void
    {
        Schema::table('record_types', function (Blueprint $table): void {
            $table->dropColumn(['has_detail_page', 'has_index_page']);
        });
    }
};
