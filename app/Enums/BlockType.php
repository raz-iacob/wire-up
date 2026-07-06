<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Support\Str;

enum BlockType: string
{
    case HERO = 'hero';
    case TEXT_IMAGE = 'text-image';
    case LOCATION = 'location';
    case ACCORDION = 'accordion';
    case GALLERY = 'gallery';
    case VIDEO = 'video';
    case PHOTO = 'photo';
    case TESTIMONIALS = 'testimonials';
    case SPONSORS = 'sponsors';
    case FEATURE_CARDS = 'feature-cards';
    case COLLECTION = 'collection';
    case BUTTONS = 'buttons';
    case AUDIO = 'audio';
    case DOWNLOADS = 'downloads';
    case RICH_TEXT = 'rich-text';
    case STATS = 'stats';
    case TEAM = 'team';
    case PRICING = 'pricing';
    case CONTACT_FORM = 'contact-form';
    case SPACER = 'spacer';
    case DIVIDER = 'divider';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type): string => $type->value, self::cases());
    }

    /**
     * @return array<int, self>
     */
    public static function sorted(): array
    {
        $cases = self::cases();
        usort($cases, fn (self $a, self $b): int => $a->label() <=> $b->label());

        return $cases;
    }

    public function label(): string
    {
        return match ($this) {
            self::HERO => __('Hero'),
            self::TEXT_IMAGE => __('Text + Image'),
            self::LOCATION => __('Location'),
            self::ACCORDION => __('Accordion'),
            self::GALLERY => __('Gallery'),
            self::VIDEO => __('Video'),
            self::PHOTO => __('Photo'),
            self::TESTIMONIALS => __('Testimonials'),
            self::SPONSORS => __('Sponsors'),
            self::FEATURE_CARDS => __('Feature Cards'),
            self::COLLECTION => __('Collection'),
            self::BUTTONS => __('Buttons'),
            self::AUDIO => __('Audio'),
            self::DOWNLOADS => __('Downloads'),
            self::RICH_TEXT => __('Rich Text'),
            self::STATS => __('Stats'),
            self::TEAM => __('Team'),
            self::PRICING => __('Pricing'),
            self::CONTACT_FORM => __('Contact Form'),
            self::SPACER => __('Spacer'),
            self::DIVIDER => __('Divider'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::HERO => 'gallery-thumbnails',
            self::TEXT_IMAGE => 'layout-list',
            self::LOCATION => 'map',
            self::ACCORDION => 'list-collapse',
            self::GALLERY => 'images',
            self::VIDEO => 'video-camera',
            self::PHOTO => 'photo',
            self::TESTIMONIALS => 'chat-bubble-left-right',
            self::SPONSORS => 'handshake',
            self::FEATURE_CARDS => 'squares-2x2',
            self::COLLECTION => 'rectangle-stack',
            self::BUTTONS => 'cursor-arrow-rays',
            self::AUDIO => 'musical-note',
            self::DOWNLOADS => 'arrow-down-tray',
            self::RICH_TEXT => 'document-text',
            self::STATS => 'chart-bar',
            self::TEAM => 'users',
            self::PRICING => 'currency-dollar',
            self::CONTACT_FORM => 'mail',
            self::SPACER => 'arrows-up-down',
            self::DIVIDER => 'minus',
        };
    }

    public function hasAnchor(): bool
    {
        return ! in_array($this, [self::SPACER, self::DIVIDER], true);
    }

    public function description(): string
    {
        return match ($this) {
            self::HERO => __('Full-width banner with a heading, subheading and background image.'),
            self::TEXT_IMAGE => __('A block of text alongside an image.'),
            self::LOCATION => __('An embedded map alongside address and contact details.'),
            self::ACCORDION => __('Collapsible sections of content, great for services or FAQs.'),
            self::GALLERY => __('A responsive grid of images and videos with an optional lightbox.'),
            self::VIDEO => __('A video player using an uploaded file, a YouTube or Vimeo link, or a direct video URL.'),
            self::PHOTO => __('A single image, shown at container width or full-bleed, with an optional link.'),
            self::TESTIMONIALS => __('Customer quotes shown in a grid, carousel or single column.'),
            self::SPONSORS => __('Sponsor and partner logos shown in a grid, marquee or grouped by tier.'),
            self::FEATURE_CARDS => __('A responsive grid of cards, each with an image or icon, a title and a short description.'),
            self::COLLECTION => __('Display records from a content type as a carousel, grid or list — by latest, category or a hand-picked set.'),
            self::BUTTONS => __('A row of call-to-action buttons linking to pages, sections or external URLs.'),
            self::AUDIO => __('An audio player for a single uploaded track or recording.'),
            self::DOWNLOADS => __('A list of downloadable files such as PDFs, documents or archives.'),
            self::RICH_TEXT => __('A standalone block of formatted text with an optional heading.'),
            self::STATS => __('Eye-catching numbers or statistics shown in a row.'),
            self::TEAM => __('A grid of team members with a photo, role, bio and social links.'),
            self::PRICING => __('Pricing plans shown side by side, each with features and a button.'),
            self::CONTACT_FORM => __('A contact form that emails you and stores each submission.'),
            self::SPACER => __('Adjustable vertical spacing between blocks.'),
            self::DIVIDER => __('A horizontal divider line in your chosen thickness.'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultContent(): array
    {
        $cta = [
            'enabled' => false,
            'text' => [],
            'link' => ['type' => 'url', 'value' => '', 'newTab' => false],
            'bg' => null,
            'textColor' => null,
        ];

        return match ($this) {
            self::HERO => [
                'align' => 'center',
                'verticalAlign' => 'center',
                'width' => 'full',
                'height' => 'auto',
                'headingColor' => null,
                'subheadingColor' => null,
                'background' => [
                    'type' => 'image',
                    'image' => null,
                    'video' => null,
                    'gradient' => ['start' => null, 'end' => null, 'direction' => 'to-b'],
                ],
                'ctaPrimary' => $cta,
                'ctaSecondary' => $cta,
            ],
            self::TEXT_IMAGE => [
                'reverseLayout' => false,
                'hasBackground' => false,
                'ctaPrimary' => $cta,
                'ctaSecondary' => $cta,
            ],
            self::LOCATION => [
                'map' => '',
                'phone' => '',
                'email' => '',
                'reverseLayout' => false,
                'hasBackground' => false,
                'directions' => [
                    'enabled' => false,
                    'text' => [],
                    'bg' => null,
                    'textColor' => null,
                ],
            ],
            self::ACCORDION => [
                'icon' => 'chevron',
                'exclusive' => true,
                'hasBackground' => false,
                'items' => [
                    ['id' => (string) Str::uuid(), 'title' => [], 'body' => []],
                ],
            ],
            self::GALLERY => [
                'media' => [],
                'columns' => 3,
                'lightbox' => true,
                'hasBackground' => false,
            ],
            self::VIDEO => [
                'source' => 'upload',
                'video' => null,
                'url' => '',
                'poster' => null,
                'aspect' => '16:9',
                'autoplay' => false,
                'loop' => false,
                'muted' => false,
                'controls' => true,
                'hasBackground' => false,
                'heading' => [],
                'intro' => [],
            ],
            self::PHOTO => [
                'image' => null,
                'width' => 'normal',
                'hasBackground' => false,
                'imageLink' => ['link' => ['type' => 'url', 'value' => '', 'newTab' => false]],
                'heading' => [],
                'intro' => [],
            ],
            self::TESTIMONIALS => [
                'layout' => 'grid',
                'columns' => 3,
                'hasBackground' => false,
                'amberStars' => false,
                'cardBg' => null,
                'cardText' => null,
                'heading' => [],
                'intro' => [],
                'items' => [
                    ['id' => (string) Str::uuid(), 'quote' => [], 'author' => [], 'role' => [], 'avatar' => null, 'rating' => 0],
                ],
            ],
            self::SPONSORS => [
                'layout' => 'grid',
                'columns' => 4,
                'hasBackground' => false,
                'grayscale' => false,
                'showNames' => false,
                'heading' => [],
                'intro' => [],
                'items' => [
                    ['id' => (string) Str::uuid(), 'logo' => null, 'name' => [], 'link' => '', 'tier' => ''],
                ],
            ],
            self::FEATURE_CARDS => [
                'columns' => 3,
                'imageHeight' => 'medium',
                'imageRounded' => false,
                'hasBackground' => false,
                'cardStyle' => true,
                'cardBg' => null,
                'cardText' => null,
                'heading' => [],
                'intro' => [],
                'items' => [
                    ['id' => (string) Str::uuid(), 'image' => null, 'title' => [], 'body' => [], 'cta' => $cta],
                ],
            ],
            self::COLLECTION => [
                'recordTypeId' => null,
                'source' => 'latest',
                'recordIds' => [],
                'categoryId' => null,
                'limit' => 12,
                'layout' => 'grid',
                'columns' => 3,
                'showImage' => true,
                'fields' => [],
                'perPage' => 6,
                'pagination' => 'none',
                'hasBackground' => false,
                'heading' => [],
                'button' => [
                    'enabled' => false,
                    'text' => [],
                    'link' => ['type' => 'url', 'value' => '', 'newTab' => false],
                ],
            ],
            self::BUTTONS => [
                'align' => 'center',
                'hasBackground' => false,
                'items' => [
                    ['id' => (string) Str::uuid(), 'text' => [], 'variant' => 'primary', 'link' => ['type' => 'url', 'value' => '', 'newTab' => false]],
                ],
            ],
            self::AUDIO => [
                'audio' => null,
                'hasBackground' => false,
                'heading' => [],
                'intro' => [],
            ],
            self::DOWNLOADS => [
                'files' => [],
                'columns' => 1,
                'hasBackground' => false,
                'heading' => [],
                'intro' => [],
            ],
            self::RICH_TEXT => [
                'heading' => [],
                'body' => [],
                'width' => 'normal',
                'align' => 'left',
                'hasBackground' => false,
            ],
            self::STATS => [
                'columns' => 4,
                'layout' => 'plain',
                'hasBackground' => false,
                'heading' => [],
                'intro' => [],
                'items' => [
                    ['id' => (string) Str::uuid(), 'value' => [], 'label' => []],
                ],
            ],
            self::TEAM => [
                'columns' => 3,
                'layout' => 'circle',
                'hasBackground' => false,
                'heading' => [],
                'intro' => [],
                'items' => [
                    ['id' => (string) Str::uuid(), 'photo' => null, 'name' => [], 'role' => [], 'bio' => [], 'socials' => ['email' => '', 'website' => '', 'linkedin' => '', 'x' => '', 'instagram' => '']],
                ],
            ],
            self::PRICING => [
                'columns' => 3,
                'hasBackground' => false,
                'heading' => [],
                'intro' => [],
                'items' => [
                    ['id' => (string) Str::uuid(), 'name' => [], 'price' => [], 'period' => [], 'description' => [], 'features' => [], 'featured' => false, 'badge' => [], 'cta' => $cta],
                ],
            ],
            self::CONTACT_FORM => [
                'formName' => '',
                'layout' => 'stacked',
                'hasBackground' => false,
                'heading' => [],
                'description' => [],
                'submitText' => [],
                'successMessage' => [],
                'recipient' => '',
                'fieldOrder' => ['name', 'email', 'message'],
                'fields' => [
                    'name' => ['required' => true, 'label' => [], 'placeholder' => [], 'column' => 'left'],
                    'email' => ['required' => true, 'label' => [], 'placeholder' => [], 'column' => 'left'],
                    'phone' => ['required' => false, 'label' => [], 'placeholder' => [], 'column' => 'left'],
                    'subject' => ['required' => false, 'label' => [], 'placeholder' => [], 'column' => 'left'],
                    'message' => ['required' => true, 'label' => [], 'placeholder' => [], 'column' => 'right'],
                ],
                'customFields' => [],
            ],
            self::SPACER => ['size' => 'medium'],
            self::DIVIDER => ['size' => 'medium'],
        };
    }

    /**
     * @param  array<string, mixed>  $content
     */
    public function editorTitle(array $content, string $locale): string
    {
        $html = (string) preg_replace('/<\/(?:p|div|li|h[1-6])>|<br\s*\/?>/i', ' ', (string) data_get($content, "heading.{$locale}"));
        $text = str(strip_tags($html))->squish()->limit(50)->value();

        return $text !== '' ? $text : $this->label();
    }

    public function adminView(): string
    {
        return "components.admin.blocks.{$this->value}";
    }

    public function frontendView(): string
    {
        return "components.site.blocks.{$this->value}";
    }
}
