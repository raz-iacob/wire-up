<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocks', function (Blueprint $table): void {
            $table->id();
            $table->morphs('blockable');
            $table->string('type');
            $table->unsignedInteger('position')->default(0);
            $table->json('content')->nullable();
            $table->timestamps();

            $table->index(['blockable_type', 'blockable_id', 'position']);
        });
    }
};
