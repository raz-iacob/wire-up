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
    case CONTACT_FORM = 'contact-form';
    case SPACER = 'spacer';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type): string => $type->value, self::cases());
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
            self::CONTACT_FORM => __('Contact Form'),
            self::SPACER => __('Spacer'),
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
            self::CONTACT_FORM => 'mail',
            self::SPACER => 'arrows-up-down',
        };
    }

    public function hasAnchor(): bool
    {
        return $this !== self::SPACER;
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
            self::CONTACT_FORM => __('A contact form that emails you and stores each submission.'),
            self::SPACER => __('Adjustable vertical spacing between blocks.'),
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
