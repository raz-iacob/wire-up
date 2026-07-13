<?php

declare(strict_types=1);

use App\Enums\BlockType;
use App\Enums\ContentStatus;
use App\Services\UpdateService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $welcomeId = $this->addPage('Welcome', 'Your new Wire-Up site is ready.', 'welcome', ContentStatus::PUBLISHED, [
            'layout' => ['hideHeader' => true, 'hideFooter' => true],
            'noindex' => true,
        ]);

        $this->addBlocks($welcomeId, $this->welcomeBlocks());

        DB::table('settings')->insert([
            'key' => 'home_page_id',
            'value' => json_encode($welcomeId),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $homeId = $this->addPage('Home', 'Welcome to our website!', 'home', ContentStatus::DRAFT);
        $this->addBlocks($homeId, $this->homeBlocks());

        $aboutId = $this->addPage('About', 'Who we are and what we do.', 'about', ContentStatus::DRAFT);
        $this->addBlocks($aboutId, $this->aboutBlocks());

        $contactId = $this->addPage('Contact', 'Get in touch with us.', 'contact', ContentStatus::DRAFT);
        $this->addBlocks($contactId, $this->contactBlocks());
    }

    private function locale(): string
    {
        return config()->string('app.locale', 'en');
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function addPage(string $title, string $description, string $slug, ContentStatus $status, array $metadata = []): int
    {
        $pageId = DB::table('pages')->insertGetId([
            'metadata' => json_encode([...$metadata, 'published_locales' => [$this->locale()]]),
            'status' => $status,
            'published_at' => $status === ContentStatus::PUBLISHED ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('translations')->insert([
            ['translatable_type' => 'page', 'translatable_id' => $pageId, 'locale' => $this->locale(), 'key' => 'title', 'body' => $title],
            ['translatable_type' => 'page', 'translatable_id' => $pageId, 'locale' => $this->locale(), 'key' => 'description', 'body' => $description],
        ]);

        DB::table('slugs')->insert([
            ['sluggable_type' => 'page', 'sluggable_id' => $pageId, 'slug' => $slug, 'locale' => $this->locale()],
        ]);

        return $pageId;
    }

    /**
     * @param  array<int, array{type: BlockType, content: array<string, mixed>}>  $blocks
     */
    private function addBlocks(int $pageId, array $blocks): void
    {
        DB::table('blocks')->insert(array_map(fn (array $block, int $position): array => [
            'blockable_type' => 'page',
            'blockable_id' => $pageId,
            'type' => $block['type']->value,
            'position' => $position,
            'content' => json_encode($block['content']),
            'created_at' => now(),
            'updated_at' => now(),
        ], $blocks, array_keys($blocks)));
    }

    /**
     * @return array<int, array{type: BlockType, content: array<string, mixed>}>
     */
    private function welcomeBlocks(): array
    {
        $logo = '<div style="display:flex;justify-content:center">'
            .'<svg width="257" height="32" viewBox="0 0 257 32" fill="currentColor" xmlns="http://www.w3.org/2000/svg" style="width:16.5rem;height:auto" role="img" aria-label="Wire-Up">'
            .'<path d="M3.5166 1.41168C-8.34597 -1.7562 13.5984 1.39494 13.7148 1.41168L23.4189 19.5816L33.123 1.41168H41.9619L51.7275 19.5816L61.4316 1.41168H71.5684L55.374 31.9058H48.0186L37.5117 12.2964L27.0654 31.9058H19.7109C19.694 31.7987 15.3877 4.58246 3.5166 1.41168ZM72.9902 1.41266H83V31.8638H72.9902V1.41266ZM85 1.41266H118.826C120.515 1.41271 122.041 1.69468 123.4 2.25934C124.801 2.82401 125.914 3.58662 126.738 4.54645C127.603 5.50622 128.036 6.55115 128.036 7.68024V15.5582C128.036 16.6873 127.603 17.7322 126.738 18.692C125.914 19.6518 124.801 20.4144 123.4 20.9791C122.041 21.5437 120.515 21.8257 118.826 21.8257L127.974 29.6187V31.8638H118.085L105.406 21.8472L93.1143 21.8687V31.8638H85V1.41266ZM171.104 7.97711H139.705V13.313H164.984V19.9205H139.705V25.2564H171.104V31.8638H130.001V1.36969H171.104V7.97711ZM179 1.41266H189V25.2564H201V1.41266H246.826C248.515 1.41271 250.041 1.69468 251.4 2.25934C252.801 2.82401 253.914 3.58662 254.738 4.54645C255.603 5.50622 256.036 6.55115 256.036 7.68024V15.5582C256.036 16.6873 255.603 17.7322 254.738 18.692C253.914 19.6518 252.801 20.4144 251.4 20.9791C250.041 21.5437 248.515 21.8257 246.826 21.8257H221.114V31.8638H213V8.86383H217.142L212.226 1.86383L207 8.86383H211V25.5963C211 26.7254 210.567 27.7702 209.702 28.73C208.878 29.6898 207.765 30.4524 206.364 31.0171C205.005 31.5817 203.479 31.8638 201.79 31.8638H188.21C186.521 31.8638 184.995 31.5817 183.636 31.0171C182.235 30.4524 181.122 29.6898 180.298 28.73C179.433 27.7702 179 26.7254 179 25.5963V1.41266ZM93.1143 15.2193H118.332V7.97711H93.1143V15.2193ZM221.114 15.2193H246.332V7.97711H221.114V15.2193Z" />'
            .'</svg></div>'
            .'<p style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)">Wire-Up</p>';

        $version = resolve(UpdateService::class)->currentVersion();
        $versionLabel = 'Wire-Up'.($version !== null ? ' v'.mb_ltrim($version, 'vV') : '').' (PHP v'.PHP_VERSION.')';

        $cards = implode('', [
            $this->welcomeCard(
                'Documentation',
                'Wire-Up has clear documentation covering everything from your first page to going live. Find guides, block references and deployment notes at <a href="https://wire-up.dev" target="_blank" rel="noopener noreferrer">wire-up.dev</a>.',
            ),
            $this->welcomeCard(
                'Pages &amp; design',
                'Compose pages from 22 ready-made content blocks, then shape the whole site — theme, colors, fonts and layout — from Settings → Design.',
            ),
            $this->welcomeCard(
                'Multilingual, SEO &amp; AI',
                'Publish in any language with localized content and slugs. Sitemaps, structured data and llms.txt ship out of the box, and every page serves clean markdown to AI agents.',
            ),
            $this->welcomeCard(
                'Getting started',
                'This site was set up with a single command — <code style="font-size:0.875em">php artisan wireup:install</code>. <a href="/login">Log in</a> to make it yours: Home, About and Contact are waiting as drafts, and this welcome page can be unpublished once your site is ready.',
            ),
        ]);

        return [
            ['type' => BlockType::SPACER, 'content' => ['size' => 'small']],
            ['type' => BlockType::RICH_TEXT, 'content' => [
                'heading' => [],
                'body' => [$this->locale() => $logo],
                'align' => 'center',
            ]],
            ['type' => BlockType::RICH_TEXT, 'content' => [
                'heading' => [],
                'body' => [$this->locale() => '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,420px),1fr));gap:1.5rem">'.$cards.'</div>'
                    .'<p style="text-align:right;font-size:0.875rem;opacity:0.6;margin-top:1.5rem">'.$versionLabel.'</p>'],
                'align' => 'left',
            ]],
        ];
    }

    private function welcomeCard(string $title, string $body): string
    {
        return '<div style="background:var(--wire-card-bg);color:var(--wire-card-text);border-radius:calc(var(--wire-radius)*1.5);padding:2rem;box-shadow:0 1px 3px rgb(0 0 0 / 0.06)">'
            .'<h2 style="margin:0 0 0.75rem;font-size:1.125rem;font-weight:600">'.$title.'</h2>'
            .'<p style="margin:0;opacity:0.75;line-height:1.65">'.$body.'</p>'
            .'</div>';
    }

    /**
     * @return array<int, array{type: BlockType, content: array<string, mixed>}>
     */
    private function homeBlocks(): array
    {
        return [
            ['type' => BlockType::HERO, 'content' => [
                'align' => 'center',
                'verticalAlign' => 'center',
                'width' => 'full',
                'height' => 'auto',
                'background' => ['type' => 'color', 'image' => null, 'video' => null, 'gradient' => ['start' => null, 'end' => null, 'direction' => 'to-b']],
                'heading' => [$this->locale() => 'Welcome to our website!'],
                'subheading' => [$this->locale() => 'We are glad you are here.'],
            ]],
            ['type' => BlockType::RICH_TEXT, 'content' => [
                'heading' => [],
                'body' => [$this->locale() => '<p>This is your homepage. Replace this text with a short introduction to what you do, and add more blocks to bring it to life.</p>'],
                'align' => 'left',
            ]],
        ];
    }

    /**
     * @return array<int, array{type: BlockType, content: array<string, mixed>}>
     */
    private function aboutBlocks(): array
    {
        return [
            ['type' => BlockType::RICH_TEXT, 'content' => [
                'heading' => [$this->locale() => 'About us'],
                'body' => [$this->locale() => '<p>Tell your story here — who you are, what you do, and why it matters. A few honest paragraphs go a long way.</p>'],
                'align' => 'left',
            ]],
        ];
    }

    /**
     * @return array<int, array{type: BlockType, content: array<string, mixed>}>
     */
    private function contactBlocks(): array
    {
        return [
            ['type' => BlockType::CONTACT_FORM, 'content' => [
                ...BlockType::CONTACT_FORM->defaultContent(),
                'heading' => [$this->locale() => 'Get in touch'],
                'description' => [$this->locale() => '<p>Have a question? Send us a message and we will get back to you.</p>'],
            ]],
        ];
    }
};
