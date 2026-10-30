<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('not_found_paths', function (Blueprint $table): void {
            $table->id();
            $table->string('path', 500)->unique();
            $table->unsignedInteger('hits')->default(1);
            $table->string('referrer', 500)->nullable();
            $table->timestamp('last_seen_at')->index();
        });
    }
};
