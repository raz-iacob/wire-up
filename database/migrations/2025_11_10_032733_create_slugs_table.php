<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slugs', function (Blueprint $table): void {
            $table->id();
            $table->string('slug');
            $table->string('locale', 6);
            $table->morphs('sluggable');
            $table->timestamps();

            $table->foreign('locale')->references('code')->on('locales')->onDelete('cascade');
            $table->unique(['slug', 'locale'], 'slugs_unique');
        });
    }
};
