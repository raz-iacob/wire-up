<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<int, array{
     *     code: string,
     *     name: string,
     *     script: string,
     *     endonym: string,
     *     regional: string|null
     * }>
     */
    protected array $locales = [
        ['code' => 'en', 'name' => 'English', 'script' => 'Latn', 'endonym' => 'English', 'regional' => 'en-US'],
        ['code' => 'zh', 'name' => 'Chinese (Simplified)', 'script' => 'Hans', 'endonym' => '简体中文', 'regional' => 'zh-CN'],
        ['code' => 'hi', 'name' => 'Hindi', 'script' => 'Deva', 'endonym' => 'हिन्दी', 'regional' => 'hi-IN'],
        ['code' => 'es', 'name' => 'Spanish', 'script' => 'Latn', 'endonym' => 'español', 'regional' => 'es-ES'],
        ['code' => 'fr', 'name' => 'French', 'script' => 'Latn', 'endonym' => 'français', 'regional' => 'fr-FR'],
        ['code' => 'ar', 'name' => 'Arabic', 'script' => 'Arab', 'endonym' => 'العربية', 'regional' => 'ar-SA'],
        ['code' => 'bn', 'name' => 'Bengali', 'script' => 'Beng', 'endonym' => 'বাংলা', 'regional' => 'bn-BD'],
        ['code' => 'pt', 'name' => 'Portuguese', 'script' => 'Latn', 'endonym' => 'português', 'regional' => 'pt-PT'],
        ['code' => 'ru', 'name' => 'Russian', 'script' => 'Cyrl', 'endonym' => 'русский', 'regional' => 'ru-RU'],
        ['code' => 'ur', 'name' => 'Urdu', 'script' => 'Arab', 'endonym' => 'اردو', 'regional' => 'ur-PK'],
        ['code' => 'id', 'name' => 'Indonesian', 'script' => 'Latn', 'endonym' => 'Bahasa Indonesia', 'regional' => 'id-ID'],
        ['code' => 'de', 'name' => 'German', 'script' => 'Latn', 'endonym' => 'Deutsch', 'regional' => 'de-DE'],
        ['code' => 'ja', 'name' => 'Japanese', 'script' => 'Jpan', 'endonym' => '日本語', 'regional' => 'ja-JP'],
        ['code' => 'sw', 'name' => 'Swahili', 'script' => 'Latn', 'endonym' => 'Kiswahili', 'regional' => 'sw-KE'],
        ['code' => 'mr', 'name' => 'Marathi', 'script' => 'Deva', 'endonym' => 'मराठी', 'regional' => 'mr-IN'],
        ['code' => 'te', 'name' => 'Telugu', 'script' => 'Telu', 'endonym' => 'తెలుగు', 'regional' => 'te-IN'],
        ['code' => 'tr', 'name' => 'Turkish', 'script' => 'Latn', 'endonym' => 'Türkçe', 'regional' => 'tr-TR'],
        ['code' => 'ko', 'name' => 'Korean', 'script' => 'Hang', 'endonym' => '한국어', 'regional' => 'ko-KR'],
        ['code' => 'ta', 'name' => 'Tamil', 'script' => 'Taml', 'endonym' => 'தமிழ்', 'regional' => 'ta-IN'],
        ['code' => 'fr-CA', 'name' => 'French (Canadian)', 'script' => 'Latn', 'endonym' => 'français canadien', 'regional' => 'fr-CA'],
        ['code' => 'vi', 'name' => 'Vietnamese', 'script' => 'Latn', 'endonym' => 'Tiếng Việt', 'regional' => 'vi-VN'],
        ['code' => 'it', 'name' => 'Italian', 'script' => 'Latn', 'endonym' => 'italiano', 'regional' => 'it-IT'],
        ['code' => 'th', 'name' => 'Thai', 'script' => 'Thai', 'endonym' => 'ไทย', 'regional' => 'th-TH'],
        ['code' => 'gu', 'name' => 'Gujarati', 'script' => 'Gujr', 'endonym' => 'ગુજરાતી', 'regional' => 'gu-IN'],
        ['code' => 'kn', 'name' => 'Kannada', 'script' => 'Knda', 'endonym' => 'ಕನ್ನಡ', 'regional' => 'kn-IN'],
        ['code' => 'fa', 'name' => 'Persian', 'script' => 'Arab', 'endonym' => 'فارسی', 'regional' => 'fa-IR'],
        ['code' => 'pl', 'name' => 'Polish', 'script' => 'Latn', 'endonym' => 'polski', 'regional' => 'pl-PL'],
        ['code' => 'uk', 'name' => 'Ukrainian', 'script' => 'Cyrl', 'endonym' => 'українська', 'regional' => 'uk-UA'],
        ['code' => 'ml', 'name' => 'Malayalam', 'script' => 'Mlym', 'endonym' => 'മലയാളം', 'regional' => 'ml-IN'],
        ['code' => 'or', 'name' => 'Oriya', 'script' => 'Orya', 'endonym' => 'ଓଡ଼ିଆ', 'regional' => 'or-IN'],
        ['code' => 'my', 'name' => 'Burmese', 'script' => 'Mymr', 'endonym' => 'မြန်မာဘာသာ', 'regional' => 'my-MM'],
        ['code' => 'pa', 'name' => 'Punjabi (Gurmukhi)', 'script' => 'Guru', 'endonym' => 'ਪੰਜਾਬੀ', 'regional' => 'pa-IN'],
        ['code' => 'jv', 'name' => 'Javanese (Latin)', 'script' => 'Latn', 'endonym' => 'Basa Jawa', 'regional' => 'jv-ID'],
        ['code' => 'ro', 'name' => 'Romanian', 'script' => 'Latn', 'endonym' => 'română', 'regional' => 'ro-RO'],
        ['code' => 'ms', 'name' => 'Malay', 'script' => 'Latn', 'endonym' => 'Bahasa Melayu', 'regional' => 'ms-MY'],
        ['code' => 'az', 'name' => 'Azerbaijani (Latin)', 'script' => 'Latn', 'endonym' => 'azərbaycanca', 'regional' => 'az-AZ'],
        ['code' => 'ne', 'name' => 'Nepali', 'script' => 'Deva', 'endonym' => 'नेपाली', 'regional' => 'ne-NP'],
        ['code' => 'si', 'name' => 'Sinhala', 'script' => 'Sinh', 'endonym' => 'සිංහල', 'regional' => 'si-LK'],
        ['code' => 'km', 'name' => 'Khmer', 'script' => 'Khmr', 'endonym' => 'ភាសាខ្មែរ', 'regional' => 'km-KH'],
        ['code' => 'tk', 'name' => 'Turkmen', 'script' => 'Cyrl', 'endonym' => 'түркменче', 'regional' => 'tk-TM'],
        ['code' => 'hu', 'name' => 'Hungarian', 'script' => 'Latn', 'endonym' => 'magyar', 'regional' => 'hu-HU'],
        ['code' => 'nl', 'name' => 'Dutch', 'script' => 'Latn', 'endonym' => 'Nederlands', 'regional' => 'nl-NL'],
        ['code' => 'he', 'name' => 'Hebrew', 'script' => 'Hebr', 'endonym' => 'עברית', 'regional' => 'he-IL'],
        ['code' => 'el', 'name' => 'Greek', 'script' => 'Grek', 'endonym' => 'Ελληνικά', 'regional' => 'el-GR'],
        ['code' => 'be', 'name' => 'Belarusian', 'script' => 'Cyrl', 'endonym' => 'беларуская', 'regional' => 'be-BY'],
        ['code' => 'cs', 'name' => 'Czech', 'script' => 'Latn', 'endonym' => 'čeština', 'regional' => 'cs-CZ'],
        ['code' => 'sv', 'name' => 'Swedish', 'script' => 'Latn', 'endonym' => 'svenska', 'regional' => 'sv-SE'],
        ['code' => 'bg', 'name' => 'Bulgarian', 'script' => 'Cyrl', 'endonym' => 'български', 'regional' => 'bg-BG'],
        ['code' => 'hr', 'name' => 'Croatian', 'script' => 'Latn', 'endonym' => 'hrvatski', 'regional' => 'hr-HR'],
        ['code' => 'sk', 'name' => 'Slovak', 'script' => 'Latn', 'endonym' => 'slovenčina', 'regional' => 'sk-SK'],
        ['code' => 'da', 'name' => 'Danish', 'script' => 'Latn', 'endonym' => 'dansk', 'regional' => 'da-DK'],
        ['code' => 'fi', 'name' => 'Finnish', 'script' => 'Latn', 'endonym' => 'suomi', 'regional' => 'fi-FI'],
        ['code' => 'no', 'name' => 'Norwegian', 'script' => 'Latn', 'endonym' => 'norsk', 'regional' => 'no-NO'],
        ['code' => 'mk', 'name' => 'Macedonian', 'script' => 'Cyrl', 'endonym' => 'македонски', 'regional' => 'mk-MK'],
        ['code' => 'sl', 'name' => 'Slovene', 'script' => 'Latn', 'endonym' => 'slovenščina', 'regional' => 'sl-SI'],
        ['code' => 'et', 'name' => 'Estonian', 'script' => 'Latn', 'endonym' => 'eesti', 'regional' => 'et-EE'],
        ['code' => 'lv', 'name' => 'Latvian', 'script' => 'Latn', 'endonym' => 'latviešu', 'regional' => 'lv-LV'],
        ['code' => 'lt', 'name' => 'Lithuanian', 'script' => 'Latn', 'endonym' => 'lietuvių', 'regional' => 'lt-LT'],
        ['code' => 'ca', 'name' => 'Catalan', 'script' => 'Latn', 'endonym' => 'català', 'regional' => 'ca-ES'],
        ['code' => 'eu', 'name' => 'Basque', 'script' => 'Latn', 'endonym' => 'euskara', 'regional' => 'eu-ES'],
        ['code' => 'gl', 'name' => 'Galician', 'script' => 'Latn', 'endonym' => 'galego', 'regional' => 'gl-ES'],
        ['code' => 'cy', 'name' => 'Welsh', 'script' => 'Latn', 'endonym' => 'Cymraeg', 'regional' => 'cy-GB'],
    ];

    public function up(): void
    {
        Schema::create('locales', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique()->comment('BCP 47 code, e.g. en, en-CA, fr, fr-CA');
            $table->string('name');
            $table->string('endonym')->nullable()->comment('Name used by native speakers, e.g. Français');
            $table->string('script')->nullable()->comment('Script or writing system, e.g. Latin, Cyrillic');
            $table->string('regional')->nullable()->comment('Regional code, e.g. en-GB, fr-CA');
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
