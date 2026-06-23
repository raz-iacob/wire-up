<?php

declare(strict_types=1);

use App\Enums\BlockType;
use App\Enums\PageStatus;
use App\Models\Page;

function publishPageWithBlocks(string $slug, array $blocks): Page
{
    $page = Page::factory()->create([
        'metadata' => ['published_locales' => ['en']],
        'status' => PageStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'title' => ucfirst($slug),
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => $slug]);
    $page->updateBlocks($blocks);

    return $page;
}

it('renders all block types on the public page', function (): void {
    publishPageWithBlocks('home-blocks', [
        ['id' => 'new-1', 'type' => 'hero', 'content' => [
            'heading' => ['en' => 'Welcome aboard'],
            'subheading' => ['en' => 'Glad to have you'],
            'align' => 'center',
        ]],
        ['id' => 'new-2', 'type' => 'text-image', 'content' => [
            'body' => ['en' => '<p>Some <strong>rich</strong> copy</p>'],
        ]],
        ['id' => 'new-3', 'type' => 'spacer', 'content' => ['size' => 'large']],
    ]);

    $this->get(route('page', 'home-blocks'))
        ->assertOk()
        ->assertSee('Welcome aboard')
        ->assertSee('Glad to have you')
        ->assertSee('<strong>rich</strong>', false)
        ->assertSee('aria-hidden="true"', false);
});

it('renders the contact form block with its present fields and submit label', function (): void {
    publishPageWithBlocks('contact', [
        ['id' => 'new-1', 'type' => 'contact-form', 'content' => array_replace_recursive(
            BlockType::CONTACT_FORM->defaultContent(),
            [
                'heading' => ['en' => '<p>Get in touch</p>'],
                'submitText' => ['en' => 'Send it over'],
                'fieldOrder' => ['name', 'email', 'phone', 'message'],
            ],
        )],
    ]);

    $this->get(route('page', 'contact'))
        ->assertOk()
        ->assertSee('Get in touch')
        ->assertSee('Name')
        ->assertSee('Email')
        ->assertSee('Phone')
        ->assertSee('Message')
        ->assertDontSee('Subject')
        ->assertSee('Send it over')
        ->assertSee('bg-(--wire-input-bg)', false)
        ->assertSee('Leave this field empty');
});

it('hides a blank label when a placeholder is set and keeps an accessible name', function (): void {
    publishPageWithBlocks('contact-nolabel', [
        ['id' => 'new-1', 'type' => 'contact-form', 'content' => array_replace_recursive(
            BlockType::CONTACT_FORM->defaultContent(),
            [
                'fieldOrder' => ['name'],
                'fields' => ['name' => ['label' => [], 'placeholder' => ['en' => 'Your name']]],
            ],
        )],
    ]);

    $this->get(route('page', 'contact-nolabel'))
        ->assertOk()
        ->assertSee('placeholder="Your name"', false)
        ->assertSee('aria-label="Your name"', false)
        ->assertDontSee('<label for="cf-name"', false);
});

it('falls back to the default label when both label and placeholder are blank', function (): void {
    publishPageWithBlocks('contact-deflabel', [
        ['id' => 'new-1', 'type' => 'contact-form', 'content' => array_replace_recursive(
            BlockType::CONTACT_FORM->defaultContent(),
            ['fieldOrder' => ['name']],
        )],
    ]);

    $this->get(route('page', 'contact-deflabel'))
        ->assertOk()
        ->assertSee('<label for="cf-name"', false)
        ->assertSee('Name');
});

it('renders custom field labels and placeholders and respects the field order', function (): void {
    publishPageWithBlocks('contact-labels', [
        ['id' => 'new-1', 'type' => 'contact-form', 'content' => array_replace_recursive(
            BlockType::CONTACT_FORM->defaultContent(),
            [
                'fieldOrder' => ['message', 'email', 'name'],
                'fields' => [
                    'name' => ['label' => ['en' => 'Your name'], 'placeholder' => ['en' => 'Jane Doe']],
                ],
            ],
        )],
    ]);

    $this->get(route('page', 'contact-labels'))
        ->assertOk()
        ->assertSee('Your name')
        ->assertSee('Jane Doe', false)
        ->assertSeeInOrder(['Message', 'Email', 'Your name']);
});

it('arranges fields across the two columns by their per-field column choice in split layout', function (): void {
    publishPageWithBlocks('contact-split', [
        ['id' => 'new-1', 'type' => 'contact-form', 'content' => array_replace_recursive(
            BlockType::CONTACT_FORM->defaultContent(),
            [
                'layout' => 'split',
                'fields' => [
                    'name' => ['column' => 'left'],
                    'email' => ['column' => 'right'],
                    'message' => ['column' => 'left'],
                ],
            ],
        )],
    ]);

    $this->get(route('page', 'contact-split'))
        ->assertOk()
        ->assertSee('md:items-start', false)
        ->assertSeeInOrder(['Name', 'Message', 'Email']);
});

it('can place a custom field ahead of the built-in fields', function (): void {
    publishPageWithBlocks('contact-mix', [
        ['id' => 'new-1', 'type' => 'contact-form', 'content' => array_replace_recursive(
            BlockType::CONTACT_FORM->defaultContent(),
            [
                'fieldOrder' => ['ref', 'name', 'email', 'message'],
                'customFields' => [
                    ['id' => 'ref', 'label' => ['en' => 'Reference code'], 'type' => 'text', 'required' => false, 'options' => ''],
                ],
            ],
        )],
    ]);

    $this->get(route('page', 'contact-mix'))
        ->assertOk()
        ->assertSeeInOrder(['Reference code', 'Name', 'Email', 'Message']);
});

it('renders block text in the current locale', function (): void {
    publishPageWithBlocks('localized', [
        ['id' => 'new-1', 'type' => 'hero', 'content' => [
            'heading' => ['en' => 'English heading', 'fr' => 'Titre francais'],
        ]],
    ]);

    $this->get(route('page', 'localized'))
        ->assertOk()
        ->assertSee('English heading')
        ->assertDontSee('Titre francais');
});

it('renders text blocks gracefully when no image is selected', function (): void {
    publishPageWithBlocks('no-image', [
        ['id' => 'new-1', 'type' => 'hero', 'content' => ['heading' => ['en' => 'Imageless hero']]],
        ['id' => 'new-2', 'type' => 'text-image', 'content' => ['body' => ['en' => '<p>Just words</p>']]],
    ]);

    $this->get(route('page', 'no-image'))
        ->assertOk()
        ->assertSee('Imageless hero')
        ->assertSee('Just words', false)
        ->assertDontSee('<img', false)
        ->assertDontSee('background-image', false)
        ->assertSee('background-color:var(--wire-header-bg)', false);
});

it('renders an image when a block has one', function (): void {
    publishPageWithBlocks('with-image', [
        ['id' => 'new-1', 'type' => 'text-image', 'content' => [
            'body' => ['en' => '<p>Look</p>'],
            'image' => ['source' => 'uploads/photo.jpg', 'crop' => [], 'alt_text' => 'A photo'],
        ]],
    ]);

    $this->get(route('page', 'with-image'))
        ->assertOk()
        ->assertSee('<img', false)
        ->assertSee('uploads/photo.jpg', false)
        ->assertSee('A photo', false);
});

it('renders a hero color gradient background', function (): void {
    publishPageWithBlocks('gradient-hero', [
        ['id' => 'new-1', 'type' => 'hero', 'content' => [
            'heading' => ['en' => 'Gradient hero'],
            'background' => ['type' => 'color', 'gradient' => ['start' => '#ff0000', 'end' => '#0000ff', 'direction' => 'to-r']],
        ]],
    ]);

    $this->get(route('page', 'gradient-hero'))
        ->assertOk()
        ->assertSee('linear-gradient(to right, #ff0000, #0000ff)', false)
        ->assertDontSee('<img', false);
});

it('falls back to theme colors for an inherited hero gradient', function (): void {
    publishPageWithBlocks('inherit-hero', [
        ['id' => 'new-1', 'type' => 'hero', 'content' => [
            'heading' => ['en' => 'Inherited hero'],
            'background' => ['type' => 'color', 'gradient' => ['direction' => 'to-b']],
        ]],
    ]);

    $this->get(route('page', 'inherit-hero'))
        ->assertOk()
        ->assertSee('linear-gradient(to bottom, var(--wire-header-bg), var(--wire-header-bg))', false)
        ->assertSee('color:var(--wire-header-text)', false);
});

it('sizes hero heading and subheading relative to the theme typography', function (): void {
    publishPageWithBlocks('typo-hero', [
        ['id' => 'new-1', 'type' => 'hero', 'content' => [
            'heading' => ['en' => 'Sized heading'],
            'subheading' => ['en' => '<p>Sized sub</p>'],
        ]],
    ]);

    $this->get(route('page', 'typo-hero'))
        ->assertOk()
        ->assertSee('calc(var(--wire-heading-size, 1.5rem) * 1.5)', false)
        ->assertSee('calc(var(--wire-body-size, 0.875rem) * 1.25)', false);
});

it('applies separate heading and subheading colors', function (): void {
    publishPageWithBlocks('two-color-hero', [
        ['id' => 'new-1', 'type' => 'hero', 'content' => [
            'heading' => ['en' => 'Coloured heading'],
            'subheading' => ['en' => '<p>Coloured sub</p>'],
            'headingColor' => '#ff0000',
            'subheadingColor' => '#00ff00',
        ]],
    ]);

    $this->get(route('page', 'two-color-hero'))
        ->assertOk()
        ->assertSee('color:#ff0000', false)
        ->assertSee('color:#00ff00', false)
        ->assertSee('Coloured heading')
        ->assertSee('Coloured sub');
});

it('renders a rich hero heading without nesting paragraphs in a heading tag', function (): void {
    publishPageWithBlocks('rich-hero-heading', [
        ['id' => 'new-1', 'type' => 'hero', 'content' => [
            'heading' => ['en' => '<p>Bold <strong>statement</strong></p>'],
        ]],
    ]);

    $this->get(route('page', 'rich-hero-heading'))
        ->assertOk()
        ->assertSee('<strong>statement</strong>', false)
        ->assertDontSee('<h2', false);
});

it('renders hero CTA buttons with resolved links', function (): void {
    $target = publishPageWithBlocks('cta-target', [
        ['id' => 'new-1', 'type' => 'spacer', 'content' => ['size' => 'small']],
    ]);

    publishPageWithBlocks('cta-hero', [
        ['id' => 'new-1', 'type' => 'hero', 'content' => [
            'heading' => ['en' => 'CTA hero'],
            'ctaPrimary' => ['enabled' => true, 'text' => ['en' => 'Get started'], 'link' => ['type' => 'anchor', 'value' => 'contact']],
            'ctaSecondary' => ['enabled' => true, 'text' => ['en' => 'Visit page'], 'link' => ['type' => 'page', 'value' => (string) $target->id]],
        ]],
    ]);

    $this->get(route('page', 'cta-hero'))
        ->assertOk()
        ->assertSee('Get started')
        ->assertSee('href="#contact"', false)
        ->assertSee('Visit page')
        ->assertSee($target->getUrl(), false);
});

it('omits hero CTA buttons that are disabled or incomplete', function (): void {
    publishPageWithBlocks('no-cta', [
        ['id' => 'new-1', 'type' => 'hero', 'content' => [
            'heading' => ['en' => 'No CTA'],
            'ctaPrimary' => ['enabled' => false, 'text' => ['en' => 'Hidden cta'], 'link' => ['type' => 'anchor', 'value' => 'x']],
            'ctaSecondary' => ['enabled' => true, 'text' => ['en' => 'Linkless cta'], 'link' => ['type' => 'url', 'value' => '']],
        ]],
    ]);

    $this->get(route('page', 'no-cta'))
        ->assertOk()
        ->assertDontSee('Hidden cta')
        ->assertDontSee('Linkless cta');
});

it('renders a full-screen hero image as a cover <img> with alt text', function (): void {
    publishPageWithBlocks('img-hero', [
        ['id' => 'new-1', 'type' => 'hero', 'content' => [
            'heading' => ['en' => 'Imaged hero'],
            'height' => 'screen',
            'background' => ['type' => 'image', 'image' => ['source' => 'uploads/hero.jpg', 'crop' => [], 'metadata' => ['alt' => 'Mountain vista']]],
        ]],
    ]);

    $this->get(route('page', 'img-hero'))
        ->assertOk()
        ->assertSee('<img', false)
        ->assertSee('uploads/hero.jpg', false)
        ->assertSee('Mountain vista', false)
        ->assertSee('object-cover', false)
        ->assertSee('min-h-svh', false)
        ->assertSee('Imaged hero');
});

it('renders a fit-content hero image inline and boxed for container width', function (): void {
    publishPageWithBlocks('boxed-hero', [
        ['id' => 'new-1', 'type' => 'hero', 'content' => [
            'heading' => ['en' => 'Boxed hero'],
            'width' => 'container', 'height' => 'auto',
            'background' => ['type' => 'image', 'image' => ['source' => 'uploads/box.jpg', 'crop' => [], 'metadata' => ['alt' => 'Boxed art']]],
        ]],
    ]);

    $this->get(route('page', 'boxed-hero'))
        ->assertOk()
        ->assertSee('<img', false)
        ->assertSee('Boxed art', false)
        ->assertSee('max-w-7xl', false)
        ->assertSee('my-12', false)
        ->assertSee('block w-full', false);
});

it('applies hero height and alignment options', function (): void {
    publishPageWithBlocks('full-hero', [
        ['id' => 'new-1', 'type' => 'hero', 'content' => [
            'heading' => ['en' => 'Tall hero'],
            'height' => 'screen', 'align' => 'left', 'verticalAlign' => 'bottom',
        ]],
    ]);

    $this->get(route('page', 'full-hero'))
        ->assertOk()
        ->assertSee('min-h-svh', false)
        ->assertSee('justify-end', false)
        ->assertSee('items-start', false);
});

it('wraps a block with an anchor id target', function (): void {
    publishPageWithBlocks('anchored', [
        ['id' => 'new-1', 'type' => 'hero', 'content' => ['heading' => ['en' => 'Jump here'], 'anchor' => 'contact']],
    ]);

    $this->get(route('page', 'anchored'))
        ->assertOk()
        ->assertSee('id="contact"', false);
});

it('renders a rich text-image heading and resolves its CTA links', function (): void {
    $target = publishPageWithBlocks('ti-cta-target', [
        ['id' => 'new-1', 'type' => 'spacer', 'content' => ['size' => 'small']],
    ]);

    publishPageWithBlocks('text-image-cta', [
        ['id' => 'new-1', 'type' => 'text-image', 'content' => [
            'heading' => ['en' => '<p>Hormones <u>off</u>?</p>'],
            'body' => ['en' => '<p>Let us fix it</p>'],
            'ctaPrimary' => ['enabled' => true, 'text' => ['en' => 'Free Consultation'], 'link' => ['type' => 'anchor', 'value' => 'book']],
            'ctaSecondary' => ['enabled' => true, 'text' => ['en' => 'Learn More'], 'link' => ['type' => 'page', 'value' => (string) $target->id]],
        ]],
    ]);

    $this->get(route('page', 'text-image-cta'))
        ->assertOk()
        ->assertSee('<u>off</u>', false)
        ->assertSee('font-size:var(--wire-heading-size, 1.5rem)', false)
        ->assertSee('Free Consultation')
        ->assertSee('href="#book"', false)
        ->assertSee('Learn More')
        ->assertSee($target->getUrl(), false);
});

it('paints a text-image background band only when enabled', function (): void {
    publishPageWithBlocks('ti-bg', [
        ['id' => 'new-1', 'type' => 'text-image', 'content' => [
            'body' => ['en' => '<p>Banded</p>'],
            'hasBackground' => true,
        ]],
    ]);

    publishPageWithBlocks('ti-no-bg', [
        ['id' => 'new-1', 'type' => 'text-image', 'content' => [
            'body' => ['en' => '<p>Plain</p>'],
            'hasBackground' => false,
        ]],
    ]);

    $this->get(route('page', 'ti-bg'))
        ->assertOk()
        ->assertSee('background-color:var(--wire-card-bg)', false)
        ->assertSee('color:var(--wire-card-text)', false);

    $this->get(route('page', 'ti-no-bg'))
        ->assertOk()
        ->assertDontSee('var(--wire-card-bg)', false);
});

it('renders a location block with an embedded map and contact details', function (): void {
    publishPageWithBlocks('loc', [
        ['id' => 'new-1', 'type' => 'location', 'content' => [
            'heading' => ['en' => '<p>Find us</p>'],
            'map' => '123 Main St, Springfield',
            'name' => ['en' => 'Acme HQ'],
            'address' => ['en' => "123 Main St\nSpringfield"],
            'hours' => ['en' => '<ul><li><strong>Mon</strong> 9–5</li></ul>'],
            'phone' => '+1 555 123 4567',
            'email' => 'hello@example.com',
        ]],
    ]);

    $this->get(route('page', 'loc'))
        ->assertOk()
        ->assertSee('Find us')
        ->assertSee('Acme HQ')
        ->assertSee('q='.urlencode('123 Main St, Springfield'), false)
        ->assertSee('output=embed', false)
        ->assertSee('href="tel:+15551234567"', false)
        ->assertSee('href="mailto:hello@example.com"', false)
        ->assertSee('whitespace-pre-line', false)
        ->assertSee('whitespace-pre-wrap', false)
        ->assertSee('<ul><li><strong>Mon</strong> 9–5</li></ul>', false);
});

it('toggles the location map side and background band', function (): void {
    publishPageWithBlocks('loc-map-left', [
        ['id' => 'new-1', 'type' => 'location', 'content' => [
            'map' => 'Berlin',
            'reverseLayout' => false,
            'hasBackground' => true,
        ]],
    ]);

    publishPageWithBlocks('loc-map-right', [
        ['id' => 'new-1', 'type' => 'location', 'content' => [
            'map' => 'Berlin',
            'reverseLayout' => true,
            'hasBackground' => false,
        ]],
    ]);

    $this->get(route('page', 'loc-map-left'))
        ->assertOk()
        ->assertDontSee('md:order-last', false)
        ->assertSee('background-color:var(--wire-card-bg)', false);

    $this->get(route('page', 'loc-map-right'))
        ->assertOk()
        ->assertSee('md:order-last', false)
        ->assertDontSee('var(--wire-card-bg)', false);
});

it('shows the location directions button only when enabled, labelled and linkable', function (): void {
    publishPageWithBlocks('loc-dir', [
        ['id' => 'new-1', 'type' => 'location', 'content' => [
            'map' => '123 Main St, Springfield',
            'directions' => ['enabled' => true, 'text' => ['en' => 'Get directions']],
        ]],
    ]);

    publishPageWithBlocks('loc-no-dir', [
        ['id' => 'new-1', 'type' => 'location', 'content' => [
            'map' => '123 Main St, Springfield',
            'directions' => ['enabled' => false, 'text' => ['en' => 'Get directions']],
        ]],
    ]);

    $this->get(route('page', 'loc-dir'))
        ->assertOk()
        ->assertSee('Get directions')
        ->assertSee('maps/search/?api=1', false)
        ->assertSee('query='.urlencode('123 Main St, Springfield'), false);

    $this->get(route('page', 'loc-no-dir'))
        ->assertOk()
        ->assertDontSee('Get directions');
});

it('renders an accordion block with its items', function (): void {
    publishPageWithBlocks('acc', [
        ['id' => 'new-1', 'type' => 'accordion', 'content' => [
            'heading' => ['en' => '<p>Our Services</p>'],
            'items' => [
                ['title' => ['en' => 'PC Service and Support'], 'body' => ['en' => '<ul><li>Repairs</li></ul>']],
                ['title' => ['en' => 'Custom PC Builds'], 'body' => ['en' => '<p>Tailored rigs</p>']],
            ],
        ]],
    ]);

    $this->get(route('page', 'acc'))
        ->assertOk()
        ->assertSee('Our Services')
        ->assertSee('PC Service and Support')
        ->assertSee('Custom PC Builds')
        ->assertSee('<ul><li>Repairs</li></ul>', false)
        ->assertSee('site-accordion', false)
        ->assertSee('data-icon="chevron"', false);
});

it('drops accordion items that have no title and no body', function (): void {
    publishPageWithBlocks('acc-empty', [
        ['id' => 'new-1', 'type' => 'accordion', 'content' => [
            'items' => [
                ['title' => ['en' => 'Real one'], 'body' => []],
                ['title' => [], 'body' => []],
            ],
        ]],
    ]);

    $this->get(route('page', 'acc-empty'))
        ->assertOk()
        ->assertSee('Real one')
        ->assertSee('data-flux-accordion-item', false);
});

it('switches the accordion indicator and background band', function (): void {
    publishPageWithBlocks('acc-pm', [
        ['id' => 'new-1', 'type' => 'accordion', 'content' => [
            'icon' => 'plus-minus',
            'hasBackground' => true,
            'items' => [['title' => ['en' => 'One'], 'body' => ['en' => '<p>x</p>']]],
        ]],
    ]);

    $this->get(route('page', 'acc-pm'))
        ->assertOk()
        ->assertSee('data-icon="plus-minus"', false)
        ->assertSee('background-color:var(--wire-card-bg)', false);
});

it('renders a gallery grid with heading, captions and a lightbox', function (): void {
    publishPageWithBlocks('gallery-grid', [
        ['id' => 'new-1', 'type' => 'gallery', 'content' => [
            'heading' => ['en' => 'Our work'],
            'columns' => 4,
            'lightbox' => true,
            'media' => [
                ['id' => 1, 'source' => 'media/photo-a.jpg', 'mime_type' => 'image/jpeg', 'metadata' => ['caption' => 'First shot']],
                ['id' => 2, 'source' => 'media/clip.mp4', 'mime_type' => 'video/mp4', 'thumbnail' => 'media/clip-thumb.jpg', 'metadata' => ['caption' => 'A clip']],
            ],
        ]],
    ]);

    $this->get(route('page', 'gallery-grid'))
        ->assertOk()
        ->assertSee('Our work')
        ->assertSee('lg:grid-cols-4', false)
        ->assertSee('media/photo-a.jpg', false)
        ->assertSee('alt="First shot"', false)
        ->assertSee('First shot')
        ->assertSee('media/clip-thumb.jpg', false)
        ->assertSee('A clip')
        ->assertSee('bg-black/90', false);
});

it('plays a gallery video inline when the lightbox is disabled', function (): void {
    publishPageWithBlocks('gallery-inline', [
        ['id' => 'new-1', 'type' => 'gallery', 'content' => [
            'lightbox' => false,
            'media' => [
                ['id' => 1, 'source' => 'media/clip.mp4', 'mime_type' => 'video/mp4', 'thumbnail' => 'media/clip-thumb.jpg'],
            ],
        ]],
    ]);

    $this->get(route('page', 'gallery-inline'))
        ->assertOk()
        ->assertSee('<video', false)
        ->assertSee('media/clip.mp4', false)
        ->assertDontSee('bg-black/90', false);
});

it('applies the gallery background band when enabled', function (): void {
    publishPageWithBlocks('gallery-bg', [
        ['id' => 'new-1', 'type' => 'gallery', 'content' => [
            'hasBackground' => true,
            'media' => [
                ['id' => 1, 'source' => 'media/photo-a.jpg', 'mime_type' => 'image/jpeg'],
            ],
        ]],
    ]);

    $this->get(route('page', 'gallery-bg'))
        ->assertOk()
        ->assertSee('background-color:var(--wire-card-bg)', false);
});

it('renders a page with no blocks without error', function (): void {
    $page = Page::factory()->create([
        'metadata' => ['published_locales' => ['en']],
        'status' => PageStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'title' => 'Empty',
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'empty']);

    $this->get(route('page', 'empty'))->assertOk();
});
