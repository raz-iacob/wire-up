<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->json('metadata')->nullable()->comment('Includes styles, restrictions, and other metadata');
            $table->string('status')->default(PageStatus::DRAFT);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });
    }
};
