<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('url', 2000);
            $table->text('secret');
            $table->json('events');
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('last_status')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_delivered_at')->nullable();
            $table->timestamps();
        });
    }
};
