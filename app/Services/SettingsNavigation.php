<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ErrorLog;
use Illuminate\Support\Facades\Gate;
use Livewire\LivewireManager;

final readonly class SettingsNavigation
{
    /**
     * @return list<array{key: string, label: string, route: string, current: bool, badge: ?string, tabs: list<array{label: string, route: string, current: bool, badge: ?string}>}>
     */
    public function sections(): array
    {
        $sections = [
            'site' => [__('Site'), [
                $this->tab(__('General'), 'admin.settings-general'),
                $this->tab(__('Identity'), 'admin.settings-identity'),
                $this->tab(__('Social'), 'admin.settings-social'),
            ]],
            'design' => [__('Design'), [
                $this->tab(__('Design'), 'admin.settings-design'),
                $this->tab(__('Menus'), 'admin.settings-menus'),
            ]],
            'content' => [__('Content'), [
                $this->tab(__('Content Types'), 'admin.settings-content-types'),
                $this->tab(__('Translations'), 'admin.settings-translations'),
            ]],
            'integrations' => [__('Integrations'), [
                $this->tab(__('App Integrations'), 'admin.settings-integrations'),
                $this->tab(__('Webhooks'), 'admin.settings-webhooks'),
                filled(config('site.ai_api_key')) ? $this->tab(__('AI Assistant'), 'admin.settings-assistant') : null,
                resolve(StripeService::class)->configured() ? $this->tab(__('Shop'), 'admin.settings-shop') : null,
            ]],
            'team' => [__('Team'), [
                Gate::allows('roles.view') ? $this->tab(__('User Roles'), 'admin.settings-roles') : null,
                Gate::allows('activity.view') ? $this->tab(__('Activity log'), 'admin.settings-activity') : null,
            ]],
            'system' => [__('System'), [
                $this->tab(__('Updates'), 'admin.settings-updates', resolve(UpdateService::class)->updateAvailable() ? '1' : null),
                $this->tab(__('Backups'), 'admin.settings-backups'),
                $this->tab(__('Export / Import'), 'admin.settings-export-import'),
            ]],
            'developer' => [__('Developer'), Gate::allows('developer') ? [
                $this->tab(__('Error reports'), 'admin.settings-developer'),
                $this->tab(__('Error log'), 'admin.settings-errors', $this->countBadge(ErrorLog::unresolvedCount())),
            ] : []],
        ];

        $built = [];

        foreach ($sections as $key => [$label, $tabs]) {
            $tabs = array_values(array_filter($tabs));

            if ($tabs === []) {
                continue;
            }

            $badges = array_values(array_filter(array_column($tabs, 'badge')));

            $built[] = [
                'key' => $key,
                'label' => $label,
                'route' => $tabs[0]['route'],
                'current' => in_array(true, array_column($tabs, 'current'), true),
                'badge' => $badges[0] ?? null,
                'tabs' => $tabs,
            ];
        }

        return $built;
    }

    /**
     * @return array{key: string, label: string, route: string, current: bool, badge: ?string, tabs: list<array{label: string, route: string, current: bool, badge: ?string}>}|null
     */
    public function currentSection(): ?array
    {
        foreach ($this->sections() as $section) {
            if ($section['current']) {
                return $section;
            }
        }

        return null;
    }

    /**
     * @return array{label: string, route: string, current: bool, badge: ?string}
     */
    private function tab(string $label, string $route, ?string $badge = null): array
    {
        return ['label' => $label, 'route' => $route, 'current' => $this->isCurrent($route), 'badge' => $badge];
    }

    private function countBadge(int $count): ?string
    {
        return $count > 0 ? (string) $count : null;
    }

    private function isCurrent(string $route): bool
    {
        $livewire = resolve(LivewireManager::class);

        if (! $livewire->isLivewireRequest()) {
            return request()->routeIs($route);
        }

        $path = mb_trim(str(route($route))->after(config()->string('app.url'))->toString(), '/') ?: '/';

        return str()->is($path, $livewire->originalPath());
    }
}
