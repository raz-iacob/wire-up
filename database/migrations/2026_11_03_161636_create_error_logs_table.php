<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('fingerprint', 64)->unique();
            $table->string('type');
            $table->text('message');
            $table->string('file');
            $table->unsignedInteger('line');
            $table->text('address')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->json('trace');
            $table->json('context');
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('last_seen_at')->index();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamps();
        });
    }
};
