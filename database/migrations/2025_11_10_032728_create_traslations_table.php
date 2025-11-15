<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table): void {
            $table->id();
            $table->string('key');
            $table->longText('body')->nullable();
            $table->string('locale', 6);
            $table->morphs('translatable');
            $table->timestamps();

            $table->foreign('locale')->references('code')->on('locales')->onDelete('cascade');
            $table->unique(['locale', 'key', 'translatable_type', 'translatable_id'], name: 'translation');
        });
    }
};
