<?php

declare(strict_types=1);

use App\Models\Settings;
use Illuminate\Support\Facades\DB;

function renameWireUpTranslationsMigration(): object
{
    return require database_path('migrations/'.basename((string) collect(glob(database_path('migrations/*_rename_wire_up_in_interface_translations.php')))->first()));
}

it('carries saved rewordings of strings that named Wire-Up over to the new spelling, in every language', function (): void {
    Settings::set(['ui_translations' => [
        'en' => ['Made with Wire-Up' => 'Built by Raz', 'Log in' => 'Sign in'],
        'fr' => ['Made with Wire-Up' => 'Fait par Raz'],
        'de' => 'not a list',
    ]]);

    renameWireUpTranslationsMigration()->up();

    expect(Settings::get('ui_translations'))->toEqual([
        'en' => ['Log in' => 'Sign in', 'Made with Wire Up' => 'Built by Raz'],
        'fr' => ['Made with Wire Up' => 'Fait par Raz'],
        'de' => 'not a list',
    ]);
});

it('keeps a rewording already saved under the new spelling', function (): void {
    $saved = ['en' => ['Made with Wire-Up' => 'Old wording', 'Made with Wire Up' => 'New wording']];
    Settings::set(['ui_translations' => $saved]);

    renameWireUpTranslationsMigration()->up();

    expect(Settings::get('ui_translations'))->toBe($saved);
});

it('does nothing on a site without saved rewordings', function (): void {
    renameWireUpTranslationsMigration()->up();

    expect(DB::table('settings')->where('key', 'ui_translations')->exists())->toBeFalse();

    DB::table('settings')->insert(['key' => 'ui_translations', 'value' => json_encode('not a map')]);

    renameWireUpTranslationsMigration()->up();

    expect(DB::table('settings')->where('key', 'ui_translations')->value('value'))->toBe(json_encode('not a map'));
});
