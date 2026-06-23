<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Block;
use App\Models\Submission;
use Illuminate\Database\Seeder;

final class SubmissionSeeder extends Seeder
{
    public function run(): void
    {
        $block = Block::query()->where('type', 'contact-form')->first();
        $pageId = $block?->blockable_type === 'page' ? $block->blockable_id : null;
        $blockId = $block?->id;

        $messages = [
            ['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'phone' => '+1 555 0100', 'subject' => 'Booking question', 'message' => "Hi, do you have availability next Tuesday afternoon for a 60-minute session?\n\nThanks!", 'form_name' => 'Massage enquiry', 'metadata' => ['service' => ['label' => 'Service', 'value' => 'Deep tissue']], 'read_at' => null],
            ['name' => 'Grace Hopper', 'email' => 'grace@example.com', 'phone' => null, 'subject' => null, 'message' => 'Loved the new site — just wanted to say hello.', 'form_name' => 'General contact', 'metadata' => [], 'read_at' => null],
            ['name' => 'Alan Turing', 'email' => 'alan@example.com', 'phone' => null, 'subject' => 'Partnership', 'message' => 'Would you be open to a collaboration? Happy to share details.', 'form_name' => 'General contact', 'metadata' => [], 'read_at' => now()->subDays(2)],
            ['name' => 'Katherine Johnson', 'email' => 'katherine@example.com', 'phone' => '+1 555 0142', 'subject' => null, 'message' => 'Please send your price list and opening hours.', 'form_name' => 'Massage enquiry', 'metadata' => ['service' => ['label' => 'Service', 'value' => 'Sports'], 'newsletter' => ['label' => 'Join newsletter', 'value' => true]], 'read_at' => null],
            ['name' => 'Linus Torvalds', 'email' => 'linus@example.com', 'phone' => null, 'subject' => 'Feedback', 'message' => 'The contact form works great. Nicely done.', 'form_name' => 'General contact', 'metadata' => [], 'read_at' => now()->subDay()],
        ];

        foreach ($messages as $index => $message) {
            Submission::factory()->create([
                'page_id' => $pageId,
                'block_id' => $blockId,
                'type' => 'contact',
                'ip' => '127.0.0.1',
                'locale' => 'en',
                'created_at' => now()->subHours($index * 7),
                'updated_at' => now()->subHours($index * 7),
                ...$message,
            ]);
        }
    }
}
