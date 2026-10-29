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
            $table->boolean('admin_only')->default(false)->after('sellable')->comment('Kept off the site; records are managed in the admin only');
        });
    }
};
