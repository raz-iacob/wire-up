<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Models\Page;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;

function pretendSiteIsDown(): void
{
    app()->instance(MaintenanceMode::class, new class implements MaintenanceMode
    {
        public function activate(array $payload): void {}

        public function deactivate(): void {}

        public function active(): bool
        {
            return true;
        }

        /**
         * @return array<string, mixed>
         */
        public function data(): array
        {
            return ['status' => 503];
        }
    });
}

it('keeps serving the admin while the site is down for maintenance', function (): void {
    $this->actingAsAdmin();

    pretendSiteIsDown();

    $this->get(route('admin.settings-updates'))->assertOk();
});

it('keeps serving the livewire endpoint while the site is down for maintenance', function (): void {
    pretendSiteIsDown();

    $response = $this->post(EndpointResolver::updatePath());

    expect($response->status())->not->toBe(503);
});

it('still takes the public site down for maintenance', function (): void {
    $page = Page::factory()->create([
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
        'metadata' => ['published_locales' => ['en']],
    ]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'still-blocked']);

    pretendSiteIsDown();

    $this->get(route('page', 'still-blocked'))->assertStatus(503);
});
