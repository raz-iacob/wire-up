<?php

declare(strict_types=1);

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

return new class extends Component
{
    public string $search = '';

    public string $topic = 'all';

    /**
     * @return array<string, array{label: string, icon: string, classes: string}>
     */
    public function categories(): array
    {
        return [
            'getting-started' => ['label' => __('Getting Started'), 'icon' => 'rocket-launch', 'classes' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'],
            'content' => ['label' => __('Content'), 'icon' => 'document-text', 'classes' => 'bg-sky-500/10 text-sky-600 dark:text-sky-400'],
            'design' => ['label' => __('Design'), 'icon' => 'swatch', 'classes' => 'bg-violet-500/10 text-violet-600 dark:text-violet-400'],
            'users' => ['label' => __('Users & Roles'), 'icon' => 'user-group', 'classes' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400'],
            'settings' => ['label' => __('Settings'), 'icon' => 'cog-6-tooth', 'classes' => 'bg-rose-500/10 text-rose-600 dark:text-rose-400'],
        ];
    }

    /**
     * @return list<array{category: string, question: string, answer: string}>
     */
    public function faqs(): array
    {
        return [
            ['category' => 'getting-started', 'question' => __('Where do I start after setting up my site?'), 'answer' => __('The Dashboard is your home base — it shows recent activity and quick links. From the left sidebar you can jump to Pages, your content types, Media, and Settings. A good first step is Settings → General to set your site title and details.')],
            ['category' => 'getting-started', 'question' => __('How do I preview my site while I work?'), 'answer' => __('Click the logo at the top of the sidebar to open the live site in a new tab. Individual pages and records also have a Preview action while editing, so you can review unpublished changes privately before going live.')],

            ['category' => 'content', 'question' => __('What is the difference between Pages and content types?'), 'answer' => __('Pages are one-off layouts you build with the block editor, like Home, About, or Contact. Content types hold repeatable records that share the same fields, such as posts or projects. You manage both from the sidebar.')],
            ['category' => 'content', 'question' => __('How do I publish or unpublish a page?'), 'answer' => __('Open the page from Pages, then use the status control in the editor. Unpublished pages stay hidden from visitors but remain fully editable and previewable by your team.')],
            ['category' => 'content', 'question' => __('How do I organise content with categories?'), 'answer' => __('Use Categories in the sidebar to create and manage taxonomies, then assign them to records while editing. Categories help group and filter related content on the front end.')],
            ['category' => 'content', 'question' => __('Where do uploaded images and files live?'), 'answer' => __('Everything you upload is stored in the Media library, opened from the sidebar. You can reuse any item across pages, records, and settings, so there is no need to upload the same file twice.')],

            ['category' => 'design', 'question' => __('How do I change my site colours and fonts?'), 'answer' => __('Go to Settings → Design. Theme tokens for colours, typography, and spacing apply across the whole front end, so a change there updates every page consistently.')],
            ['category' => 'design', 'question' => __('Can I use my own logo and favicon?'), 'answer' => __('Yes — set them under Settings → Identity. They appear in the admin sidebar, the browser tab, and on your live site.')],
            ['category' => 'design', 'question' => __('How do I edit the site navigation menus?'), 'answer' => __('Settings → Menus lets you build header and footer menus, link to pages or custom URLs, and reorder items by dragging.')],
            ['category' => 'design', 'question' => __('Can I switch the admin between light and dark mode?'), 'answer' => __('Yes. Open the account menu at the top right, choose Account → Appearance, and pick Light, Dark, or System. Your choice is saved to your profile.')],

            ['category' => 'users', 'question' => __('How do I invite a team member?'), 'answer' => __('Go to Users in the sidebar and add a new user with their email and role. They will be able to sign in to the admin with the permissions their role allows.')],
            ['category' => 'users', 'question' => __('How do roles and permissions work?'), 'answer' => __('Each user has a role, and roles define what they can see and do. Manage roles and their permissions under Settings → Roles — for example, limiting an editor to content while reserving settings for administrators.')],

            ['category' => 'settings', 'question' => __('How do I connect Google Analytics?'), 'answer' => __('Add your credentials under Settings → Integrations. Once connected, visitor stats appear on the Analytics page in the sidebar.')],
            ['category' => 'settings', 'question' => __('Can I run my site in more than one language?'), 'answer' => __('Yes. Settings → Translations lets you manage locales and translate both your content and the interface labels your visitors see.')],
            ['category' => 'settings', 'question' => __('How do I keep Wire-Up up to date?'), 'answer' => __('Settings → Updates shows your current version and any available release. You can review the changelog and apply updates from there.')],
            ['category' => 'settings', 'question' => __('Where do I update my own name, email, or password?'), 'answer' => __('Open the account menu at the top right and choose Account. From there you can edit your profile, and change your password under Account → Password.')],
        ];
    }

    /**
     * @return list<array{category: string, question: string, answer: string}>
     */
    #[Computed]
    public function results(): array
    {
        $search = mb_trim(mb_strtolower($this->search));

        return array_values(array_filter($this->faqs(), function (array $faq) use ($search): bool {
            if ($this->topic !== 'all' && $faq['category'] !== $this->topic) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            return str_contains(mb_strtolower($faq['question'].' '.$faq['answer']), $search);
        }));
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Help'))
            ->layout('layouts::admin');
    }
};
?>

@php
    $categories = $this->categories();
@endphp

<div class="mx-auto max-w-4xl space-y-8">
    <div>
        <flux:heading size="xl">{{ __('Help & Support') }}</flux:heading>
        <flux:subheading>{{ __('Find answers to common questions about running your site.') }}</flux:subheading>
    </div>

    <div class="space-y-4">
        <flux:input
            wire:model.live.debounce.250ms="search"
            type="search"
            icon="magnifying-glass"
            placeholder="{{ __('Search for answers…') }}"
            clearable
        />

        <div class="flex flex-wrap gap-2">
            <flux:button size="sm" wire:click="$set('topic', 'all')" :variant="$topic === 'all' ? 'primary' : 'filled'">
                {{ __('All Topics') }}
            </flux:button>
            @foreach ($categories as $key => $category)
                <flux:button size="sm" wire:click="$set('topic', '{{ $key }}')" :variant="$topic === $key ? 'primary' : 'filled'">
                    {{ $category['label'] }}
                </flux:button>
            @endforeach
        </div>
    </div>

    @if ($this->results === [])
        <flux:card class="flex flex-col items-center gap-2 py-12 text-center">
            <flux:icon name="magnifying-glass" class="size-8 text-zinc-400" />
            <flux:heading>{{ __('No results found') }}</flux:heading>
            <flux:subheading>{{ __('Try a different search or topic.') }}</flux:subheading>
        </flux:card>
    @else
        <flux:accordion transition>
            @foreach ($this->results as $faq)
                @php($category = $categories[$faq['category']])
                <flux:accordion.item wire:key="faq-{{ $loop->index }}-{{ $faq['category'] }}">
                    <flux:accordion.heading>
                        <span class="flex items-center gap-3">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg {{ $category['classes'] }}">
                                <flux:icon :name="$category['icon']" variant="micro" class="size-4" />
                            </span>
                            <span>{{ $faq['question'] }}</span>
                        </span>
                    </flux:accordion.heading>
                    <flux:accordion.content>
                        <div class="ps-11">
                            <flux:text>{{ $faq['answer'] }}</flux:text>
                        </div>
                    </flux:accordion.content>
                </flux:accordion.item>
            @endforeach
        </flux:accordion>
    @endif
</div>

@section('header-content')
    <flux:breadcrumbs>
        <flux:breadcrumbs.item class="pl-3 md:pl-0">{{ __('Help') }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>
@endsection
