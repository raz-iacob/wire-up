<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<int, array{code: string, name: string, script: string, endonym: string}>
     */
    protected array $locales = [
        ['code' => 'en', 'name' => 'English', 'script' => 'Latn', 'endonym' => 'English'],
        ['code' => 'zh', 'name' => 'Chinese (Simplified)', 'script' => 'Hans', 'endonym' => '简体中文'],
        ['code' => 'hi', 'name' => 'Hindi', 'script' => 'Deva', 'endonym' => 'हिन्दी'],
        ['code' => 'es', 'name' => 'Spanish', 'script' => 'Latn', 'endonym' => 'español'],
        ['code' => 'fr', 'name' => 'French', 'script' => 'Latn', 'endonym' => 'français'],
        ['code' => 'ar', 'name' => 'Arabic', 'script' => 'Arab', 'endonym' => 'العربية'],
        ['code' => 'bn', 'name' => 'Bengali', 'script' => 'Beng', 'endonym' => 'বাংলা'],
        ['code' => 'pt', 'name' => 'Portuguese', 'script' => 'Latn', 'endonym' => 'português'],
        ['code' => 'ru', 'name' => 'Russian', 'script' => 'Cyrl', 'endonym' => 'русский'],
        ['code' => 'ur', 'name' => 'Urdu', 'script' => 'Arab', 'endonym' => 'اردو'],
        ['code' => 'id', 'name' => 'Indonesian', 'script' => 'Latn', 'endonym' => 'Bahasa Indonesia'],
        ['code' => 'de', 'name' => 'German', 'script' => 'Latn', 'endonym' => 'Deutsch'],
        ['code' => 'ja', 'name' => 'Japanese', 'script' => 'Jpan', 'endonym' => '日本語'],
        ['code' => 'sw', 'name' => 'Swahili', 'script' => 'Latn', 'endonym' => 'Kiswahili'],
        ['code' => 'mr', 'name' => 'Marathi', 'script' => 'Deva', 'endonym' => 'मराठी'],
        ['code' => 'te', 'name' => 'Telugu', 'script' => 'Telu', 'endonym' => 'తెలుగు'],
        ['code' => 'tr', 'name' => 'Turkish', 'script' => 'Latn', 'endonym' => 'Türkçe'],
        ['code' => 'ko', 'name' => 'Korean', 'script' => 'Hang', 'endonym' => '한국어'],
        ['code' => 'ta', 'name' => 'Tamil', 'script' => 'Taml', 'endonym' => 'தமிழ்'],
        ['code' => 'fr-CA', 'name' => 'French (Canadian)', 'script' => 'Latn', 'endonym' => 'français canadien'],
        ['code' => 'vi', 'name' => 'Vietnamese', 'script' => 'Latn', 'endonym' => 'Tiếng Việt'],
        ['code' => 'it', 'name' => 'Italian', 'script' => 'Latn', 'endonym' => 'italiano'],
        ['code' => 'th', 'name' => 'Thai', 'script' => 'Thai', 'endonym' => 'ไทย'],
        ['code' => 'gu', 'name' => 'Gujarati', 'script' => 'Gujr', 'endonym' => 'ગુજરાતી'],
        ['code' => 'kn', 'name' => 'Kannada', 'script' => 'Knda', 'endonym' => 'ಕನ್ನಡ'],
        ['code' => 'fa', 'name' => 'Persian', 'script' => 'Arab', 'endonym' => 'فارسی'],
        ['code' => 'pl', 'name' => 'Polish', 'script' => 'Latn', 'endonym' => 'polski'],
        ['code' => 'uk', 'name' => 'Ukrainian', 'script' => 'Cyrl', 'endonym' => 'українська'],
        ['code' => 'ml', 'name' => 'Malayalam', 'script' => 'Mlym', 'endonym' => 'മലയാളം'],
        ['code' => 'or', 'name' => 'Oriya', 'script' => 'Orya', 'endonym' => 'ଓଡ଼ିଆ'],
        ['code' => 'my', 'name' => 'Burmese', 'script' => 'Mymr', 'endonym' => 'မြန်မာဘာသာ'],
        ['code' => 'pa', 'name' => 'Punjabi (Gurmukhi)', 'script' => 'Guru', 'endonym' => 'ਪੰਜਾਬੀ'],
        ['code' => 'jv', 'name' => 'Javanese (Latin)', 'script' => 'Latn', 'endonym' => 'Basa Jawa'],
        ['code' => 'ro', 'name' => 'Romanian', 'script' => 'Latn', 'endonym' => 'română'],
        ['code' => 'ms', 'name' => 'Malay', 'script' => 'Latn', 'endonym' => 'Bahasa Melayu'],
        ['code' => 'az', 'name' => 'Azerbaijani (Latin)', 'script' => 'Latn', 'endonym' => 'azərbaycanca'],
        ['code' => 'ne', 'name' => 'Nepali', 'script' => 'Deva', 'endonym' => 'नेपाली'],
        ['code' => 'si', 'name' => 'Sinhala', 'script' => 'Sinh', 'endonym' => 'සිංහල'],
        ['code' => 'km', 'name' => 'Khmer', 'script' => 'Khmr', 'endonym' => 'ភាសាខ្មែរ'],
        ['code' => 'tk', 'name' => 'Turkmen', 'script' => 'Cyrl', 'endonym' => 'түркменче'],
        ['code' => 'hu', 'name' => 'Hungarian', 'script' => 'Latn', 'endonym' => 'magyar'],
        ['code' => 'nl', 'name' => 'Dutch', 'script' => 'Latn', 'endonym' => 'Nederlands'],
        ['code' => 'he', 'name' => 'Hebrew', 'script' => 'Hebr', 'endonym' => 'עברית'],
        ['code' => 'el', 'name' => 'Greek', 'script' => 'Grek', 'endonym' => 'Ελληνικά'],
        ['code' => 'be', 'name' => 'Belarusian', 'script' => 'Cyrl', 'endonym' => 'беларуская'],
        ['code' => 'cs', 'name' => 'Czech', 'script' => 'Latn', 'endonym' => 'čeština'],
        ['code' => 'sv', 'name' => 'Swedish', 'script' => 'Latn', 'endonym' => 'svenska'],
        ['code' => 'bg', 'name' => 'Bulgarian', 'script' => 'Cyrl', 'endonym' => 'български'],
        ['code' => 'hr', 'name' => 'Croatian', 'script' => 'Latn', 'endonym' => 'hrvatski'],
        ['code' => 'sk', 'name' => 'Slovak', 'script' => 'Latn', 'endonym' => 'slovenčina'],
        ['code' => 'da', 'name' => 'Danish', 'script' => 'Latn', 'endonym' => 'dansk'],
        ['code' => 'fi', 'name' => 'Finnish', 'script' => 'Latn', 'endonym' => 'suomi'],
        ['code' => 'no', 'name' => 'Norwegian', 'script' => 'Latn', 'endonym' => 'norsk'],
        ['code' => 'mk', 'name' => 'Macedonian', 'script' => 'Cyrl', 'endonym' => 'македонски'],
        ['code' => 'sl', 'name' => 'Slovene', 'script' => 'Latn', 'endonym' => 'slovenščina'],
        ['code' => 'et', 'name' => 'Estonian', 'script' => 'Latn', 'endonym' => 'eesti'],
        ['code' => 'lv', 'name' => 'Latvian', 'script' => 'Latn', 'endonym' => 'latviešu'],
        ['code' => 'lt', 'name' => 'Lithuanian', 'script' => 'Latn', 'endonym' => 'lietuvių'],
        ['code' => 'ca', 'name' => 'Catalan', 'script' => 'Latn', 'endonym' => 'català'],
        ['code' => 'eu', 'name' => 'Basque', 'script' => 'Latn', 'endonym' => 'euskara'],
        ['code' => 'gl', 'name' => 'Galician', 'script' => 'Latn', 'endonym' => 'galego'],
        ['code' => 'cy', 'name' => 'Welsh', 'script' => 'Latn', 'endonym' => 'Cymraeg'],
    ];

    public function up(): void
    {
        Schema::create('locales', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique()->comment('BCP 47 code, e.g. en, en-CA, fr, fr-CA');
            $table->string('name');
            $table->string('endonym')->nullable()->comment('Name used by native speakers, e.g. Français');
            $table->string('script')->nullable()->comment('Script or writing system, e.g. Latin, Cyrillic');
            $table->boolean('rtl')->default(false)->comment('Right-to-left script');
            $table->boolean('active')->default(false)->comment('Enabled for use on the site');
            $table->boolean('published')->default(false)->comment('Visible to the public');
            $table->timestamps();
        });

        foreach ($this->locales as $locale) {
            DB::table('locales')->insert([
                'code' => $locale['code'],
                'name' => $locale['name'],
                'endonym' => $locale['endonym'],
                'script' => $locale['script'],
                'rtl' => in_array($locale['script'], ['Arab', 'Hebr', 'Mong', 'Tfng', 'Thaa']),
                'active' => $locale['code'] === 'en',
                'published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
