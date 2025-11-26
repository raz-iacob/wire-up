<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->comment('photo, video, document, audio');
            $table->string('source')->comment('S3 key for uploaded files or external URL for videos');
            $table->string('etag')->nullable()->comment('Etag for S3 file versioning and duplicate detection');
            $table->string('filename')->nullable();
            $table->string('alt_text')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('thumbnail')->nullable()->comment('S3 key for uploaded thumbnails');
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedBigInteger('duration')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->unique('etag');
        });

        Schema::create('mediables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->onDelete('cascade');
            $table->morphs('mediable');
            $table->string('locale', 6);
            $table->string('role')->nullable()->comment('e.g., cover, profile, attachment');
            $table->json('crop')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->foreign('locale')->references('code')->on('locales')->onDelete('cascade');
            $table->index(['mediable_type', 'mediable_id'], 'mediable');
        });
    }
};
