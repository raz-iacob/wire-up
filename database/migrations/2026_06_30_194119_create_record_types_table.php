<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_types', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('slug_prefix')->unique();
            $table->string('icon')->default('rectangle-stack');
            $table->string('name');
            $table->json('fields')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }
};
