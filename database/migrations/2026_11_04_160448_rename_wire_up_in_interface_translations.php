<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $row = DB::table('settings')->where('key', 'ui_translations')->first(['id', 'value']);

        if ($row === null) {
            return;
        }

        $translations = json_decode((string) $row->value, true);

        if (! is_array($translations)) {
            return;
        }

        $renamed = array_map(fn (mixed $lines): mixed => is_array($lines) ? $this->renameKeys($lines) : $lines, $translations);

        if ($renamed === $translations) {
            return;
        }

        DB::table('settings')->where('id', $row->id)->update(['value' => json_encode($renamed)]);

        cache()->forget('site-config');
    }

    /**
     * @param  array<array-key, mixed>  $lines
     * @return array<array-key, mixed>
     */
    private function renameKeys(array $lines): array
    {
        foreach ($lines as $source => $replacement) {
            $target = str_replace('Wire-Up', 'Wire Up', (string) $source);

            if ($target !== $source && ! array_key_exists($target, $lines)) {
                $lines[$target] = $replacement;
                unset($lines[$source]);
            }
        }

        return $lines;
    }
};
