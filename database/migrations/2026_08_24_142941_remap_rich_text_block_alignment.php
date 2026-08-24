<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('blocks')->where('type', 'rich-text')->orderBy('id')->each(function (object $block): void {
            $content = json_decode((string) $block->content, true);

            if (! is_array($content)) {
                return;
            }

            $width = $content['width'] ?? 'normal';

            if (! in_array($width, ['narrow', 'narrow-left'], true)) {
                return;
            }

            $content['width'] = 'narrow';
            $content['align'] = $width === 'narrow-left' ? 'left' : 'center';

            DB::table('blocks')->where('id', $block->id)->update(['content' => json_encode($content)]);
        });
    }
};
