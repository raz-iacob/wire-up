<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revisions', function (Blueprint $table): void {
            $table->id();
            $table->morphs('revisionable');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 20);
            $table->string('hash', 64);
            $table->json('content');
            $table->timestamp('created_at')->nullable();

            $table->index(['revisionable_type', 'revisionable_id', 'id']);
        });
    }
};
