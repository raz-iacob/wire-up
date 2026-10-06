<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<string, array<int, string>>
     */
    private const array ACTIONS = [
        'admin' => ['view', 'create', 'edit', 'delete'],
        'editor' => ['view', 'create', 'edit', 'delete'],
        'author' => ['view', 'create', 'edit'],
    ];

    public function up(): void
    {
        $recordTypeKeys = DB::table('record_types')->pluck('key')->map(fn (mixed $key): string => (string) $key);

        foreach (DB::table('roles')->whereIn('key', array_keys(self::ACTIONS))->get(['id', 'key', 'abilities']) as $role) {
            $abilities = json_decode((string) $role->abilities, true);
            $abilities = is_array($abilities) ? array_values(array_filter($abilities, is_string(...))) : [];
            $granted = $abilities;

            foreach ($recordTypeKeys as $recordTypeKey) {
                $prefix = 'records.'.$recordTypeKey.'.';

                if (array_any($abilities, fn (string $ability): bool => str_starts_with($ability, $prefix))) {
                    continue;
                }

                foreach (self::ACTIONS[(string) $role->key] as $action) {
                    $granted[] = $prefix.$action;
                }
            }

            if ($granted !== $abilities) {
                DB::table('roles')->where('id', $role->id)->update(['abilities' => json_encode($granted)]);
            }
        }
    }

    public function down(): void {}
};
