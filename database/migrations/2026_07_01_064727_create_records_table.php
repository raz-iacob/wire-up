<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('record_type_id')->constrained()->cascadeOnDelete();
            $table->json('data')->nullable();
            $table->json('metadata')->nullable();
            $table->string('status')->default(ContentStatus::DRAFT->value);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['record_type_id', 'status', 'published_at']);
        });
    }
};
