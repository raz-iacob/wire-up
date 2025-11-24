<?php

declare(strict_types=1);

use App\Enums\PageStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->json('metadata')->nullable()->comment('Includes styles, restrictions, and other metadata');
            $table->string('status')->default(PageStatus::DRAFT);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });

        $this->addHomePage();
    }

    private function addHomePage(): void
    {
        $title = 'Home';
        $description = 'Welcome to our website!';

        $pageId = DB::table('pages')->insertGetId([
            'metadata' => json_encode(['layout' => 'default']),
            'status' => PageStatus::PUBLISHED,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('translations')->insert([
            [
                'translatable_type' => 'page',
                'translatable_id' => $pageId,
                'locale' => 'en',
                'key' => 'title',
                'body' => $title,
            ],
            [
                'translatable_type' => 'page',
                'translatable_id' => $pageId,
                'locale' => 'en',
                'key' => 'description',
                'body' => $description,
            ],
        ]);

        DB::table('slugs')->insert([
            [
                'sluggable_type' => 'page',
                'sluggable_id' => $pageId,
                'slug' => Str::slug($title),
                'locale' => 'en',
            ],
        ]);
    }
};
