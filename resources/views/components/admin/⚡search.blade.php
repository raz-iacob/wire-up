<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use App\Models\User;
use App\Services\GoogleAnalyticsDataService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

return new class extends Component
{
    private const int PER_GROUP = 5;

    public string $query = '';

    /**
     * @return array<int, array{heading: string, items: array<int, array{label: string, description: string, icon: string, url: string}>}>
     */
    #[Computed]
    public function groups(): array
    {
        $term = mb_trim($this->query);

        $groups = [
            ['heading' => __('Pages'), 'items' => $this->pages($term)],
            ...$this->recordGroups($term),
            ['heading' => __('Categories'), 'items' => $this->categories($term)],
            ['heading' => __('Users'), 'items' => $this->users($term)],
            ['heading' => __('Go to'), 'items' => $this->destinations($term)],
        ];

        return array_values(array_filter($groups, fn (array $group): bool => $group['items'] !== []));
    }

    public function render(): View
    {
        return $this->view();
    }

    /**
     * @return array<int, array{label: string, description: string, icon: string, url: string}>
     */
    private function pages(string $term): array
    {
        if ($term === '' || ! auth()->user()?->can('pages.view')) {
            return [];
        }

        return Page::query()
            ->with(['translations', 'slugs'])
            ->whereTranslationLike('title', $term)
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Page $page): array => [
                'label' => $page->title ?: __('Untitled'),
                'description' => $page->isLiveInLocale() ? __('Published') : __('Draft'),
                'icon' => 'document',
                'url' => route('admin.pages-edit', $page),
            ])
            ->all();
    }

    /**
     * @return array<int, array{heading: string, items: array<int, array{label: string, description: string, icon: string, url: string}>}>
     */
    private function recordGroups(string $term): array
    {
        if ($term === '') {
            return [];
        }

        return RecordType::ordered()
            ->filter(fn (RecordType $type): bool => (bool) auth()->user()?->can("records.{$type->key}.view"))
            ->map(fn (RecordType $type): array => [
                'heading' => $type->name,
                'items' => $this->records($type, $term),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, description: string, icon: string, url: string}>
     */
    private function records(RecordType $type, string $term): array
    {
        return Record::query()
            ->with('translations')
            ->where('record_type_id', $type->id)
            ->matchingSearch($term, $type)
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Record $record): array => [
                'label' => $record->title ?: __('Untitled'),
                'description' => $record->isLiveInLocale() ? __('Published') : __('Draft'),
                'icon' => 'rectangle-stack',
                'url' => route('admin.records-edit', [$type, $record]),
            ])
            ->all();
    }

    /**
     * @return array<int, array{label: string, description: string, icon: string, url: string}>
     */
    private function categories(string $term): array
    {
        if ($term === '' || ! auth()->user()?->can('categories.view')) {
            return [];
        }

        return Category::query()
            ->with('translations')
            ->whereTranslationLike('name', $term)
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Category $category): array => [
                'label' => $category->name ?: __('Untitled'),
                'description' => __('Category'),
                'icon' => 'tag',
                'url' => route('admin.categories-edit', $category),
            ])
            ->all();
    }

    /**
     * @return array<int, array{label: string, description: string, icon: string, url: string}>
     */
    private function users(string $term): array
    {
        if ($term === '' || ! auth()->user()?->can('users.view')) {
            return [];
        }

        return User::query()
            ->whereAny(['name', 'email'], 'like', "%{$term}%")
            ->orderBy('name')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (User $user): array => [
                'label' => $user->name ?: $user->email,
                'description' => $user->email,
                'icon' => 'user',
                'url' => route('admin.users-edit', $user),
            ])
            ->all();
    }

    /**
     * @return array<int, array{label: string, description: string, icon: string, url: string}>
     */
    private function destinations(string $term): array
    {
        return collect($this->places())
            ->filter(fn (array $place): bool => $place['can'] === null || (bool) auth()->user()?->can($place['can']))
            ->filter(fn (array $place): bool => $term === '' || $this->matches($place, $term))
            ->map(fn (array $place): array => [
                'label' => $place['label'],
                'description' => $place['description'],
                'icon' => $place['icon'],
                'url' => $place['url'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, description: string, icon: string, keywords: string, url: string, can: ?string}>
     */
    private function places(): array
    {
        $settings = fn (string $label, string $route, string $keywords): array => [
            'label' => $label,
            'description' => __('Settings'),
            'icon' => 'cog-6-tooth',
            'keywords' => $keywords,
            'url' => route("admin.{$route}"),
            'can' => 'settings.view',
        ];

        $recordTypes = RecordType::ordered()
            ->map(fn (RecordType $type): array => [
                'label' => $type->name,
                'description' => __('Content'),
                'icon' => 'rectangle-stack',
                'keywords' => 'records content '.mb_strtolower($type->name),
                'url' => route('admin.records-index', $type),
                'can' => "records.{$type->key}.view",
            ])
            ->all();

        $analytics = resolve(GoogleAnalyticsDataService::class)->configured()
            ? [['label' => __('Analytics'), 'description' => '', 'icon' => 'chart-pie', 'keywords' => 'stats reports visitors traffic', 'url' => route('admin.analytics'), 'can' => null]]
            : [];

        return [
            ['label' => __('Dashboard'), 'description' => '', 'icon' => 'home', 'keywords' => 'home overview start', 'url' => route('admin.dashboard'), 'can' => null],
            ...$analytics,
            ['label' => __('Pages'), 'description' => __('Content'), 'icon' => 'document', 'keywords' => 'content blocks', 'url' => route('admin.pages-index'), 'can' => 'pages.view'],
            ...$recordTypes,
            ['label' => __('Categories'), 'description' => __('Content'), 'icon' => 'tag', 'keywords' => 'taxonomy grouping tags', 'url' => route('admin.categories-index'), 'can' => 'categories.view'],
            ['label' => __('Inbox'), 'description' => '', 'icon' => 'inbox', 'keywords' => 'messages contact form submissions enquiries', 'url' => route('admin.inbox-index'), 'can' => 'inbox.view'],
            ['label' => __('Users'), 'description' => '', 'icon' => 'user', 'keywords' => 'accounts members staff people', 'url' => route('admin.users-index'), 'can' => 'users.view'],
            ['label' => __('Roles'), 'description' => __('Settings'), 'icon' => 'shield-check', 'keywords' => 'permissions abilities access', 'url' => route('admin.settings-roles'), 'can' => 'roles.view'],
            $settings(__('General'), 'settings-general', 'homepage timezone contact email registration'),
            $settings(__('Content types'), 'settings-content-types', 'records blueprint fields presets'),
            $settings(__('Identity'), 'settings-identity', 'title tagline favicon share image seo noindex'),
            $settings(__('Design'), 'settings-design', 'theme colours colors fonts logo appearance css width'),
            $settings(__('Menus'), 'settings-menus', 'navigation header footer sidebar links'),
            $settings(__('Translations'), 'settings-translations', 'languages locales interface strings'),
            $settings(__('Social'), 'settings-social', 'facebook instagram youtube linkedin x links'),
            $settings(__('Integrations'), 'settings-integrations', 'slack email smtp pexels analytics maps assistant api keys'),
            $settings(__('Updates'), 'settings-updates', 'version upgrade release changelog'),
            $settings(__('Export and import'), 'settings-export-import', 'bundle backup migrate transfer'),
            ['label' => __('Help'), 'description' => '', 'icon' => 'question-mark-circle', 'keywords' => 'docs documentation guide support', 'url' => route('admin.help'), 'can' => null],
            ['label' => __('Your profile'), 'description' => __('Account'), 'icon' => 'user-circle', 'keywords' => 'name email avatar photo', 'url' => route('admin.account-profile'), 'can' => null],
            ['label' => __('Your password'), 'description' => __('Account'), 'icon' => 'key', 'keywords' => 'change password security', 'url' => route('admin.account-password'), 'can' => null],
            ['label' => __('Two-factor authentication'), 'description' => __('Account'), 'icon' => 'lock-closed', 'keywords' => 'security 2fa authenticator recovery codes', 'url' => route('admin.account-security'), 'can' => null],
            ['label' => __('Appearance'), 'description' => __('Account'), 'icon' => 'swatch', 'keywords' => 'dark light theme mode', 'url' => route('admin.account-appearance'), 'can' => null],
        ];
    }

    /**
     * @param  array{label: string, description: string, keywords: string, url: string, can: ?string, icon: string}  $place
     */
    private function matches(array $place, string $term): bool
    {
        $haystack = mb_strtolower($place['label'].' '.$place['description'].' '.$place['keywords']);

        return str_contains($haystack, mb_strtolower($term));
    }
};
?>

<div>
    <flux:modal name="admin-search" variant="bare" class="w-full max-w-xl" x-on:close="$wire.set('query', '')">
        <flux:command filter="manual">
            <flux:command.input
                :placeholder="__('Search pages, records, users, settings…')"
                closable
                autofocus
                wire:model.live.debounce.250ms="query"
            />

            <flux:command.items class="max-h-96">
                @foreach ($this->groups as $group)
                    <div
                        class="px-2 pt-3 pb-1 text-xs font-medium text-zinc-400 dark:text-zinc-400"
                        wire:key="heading-{{ $loop->index }}"
                    >
                        {{ $group['heading'] }}
                    </div>

                    @foreach ($group['items'] as $item)
                        <flux:command.item
                            :icon="$item['icon']"
                            wire:key="item-{{ $loop->parent->index }}-{{ $loop->index }}"
                            data-url="{{ $item['url'] }}"
                            x-on:click="
                                $flux.modal('admin-search').close();
                                Livewire.navigate($el.dataset.url);
                            "
                        >
                            <span class="truncate">{{ $item['label'] }}</span>

                            @if ($item['description'] !== '')
                                <span class="ms-2 truncate text-xs text-zinc-400">{{ $item['description'] }}</span>
                            @endif
                        </flux:command.item>
                    @endforeach
                @endforeach

                @if ($this->groups === [])
                    <div class="flex h-10 items-center justify-center text-sm font-medium text-zinc-500 dark:text-zinc-400">
                        {{ __('No results found') }}
                    </div>
                @endif
            </flux:command.items>
        </flux:command>
    </flux:modal>
</div>
