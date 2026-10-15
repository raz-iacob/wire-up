<?php

declare(strict_types=1);

use App\Actions\CreateRecordAction;
use App\Actions\DeletePageAction;
use App\Actions\UpdatePageAction;
use App\Actions\UpdateRecordAction;
use App\Ai\Agents\SiteAssistant;
use App\Enums\ContentStatus;
use App\Enums\RevisionSource;
use App\Mcp\Servers\WireUpServer;
use App\Mcp\Tools\UpdatePageTool;
use App\Models\Category;
use App\Models\Media;
use App\Models\Page;
use App\Models\RecordType;
use App\Models\Revision;
use App\Models\User;
use Laravel\Ai\Responses\Data\ToolCall;
use Livewire\Livewire;

function revisedPage(string $title = 'Original'): Page
{
    $page = Page::factory()->create(['title' => $title, 'status' => ContentStatus::PUBLISHED, 'published_at' => now()->subDay()]);

    return Page::query()->findOrFail($page->id);
}

it('keeps the content before the first edit and the content after it', function (): void {
    $editor = User::factory()->create(['name' => 'Grace Editor']);
    $this->actingAs($editor);
    $page = revisedPage();

    resolve(UpdatePageAction::class)->handle($page, ['title' => ['en' => 'Renamed'], 'status' => ContentStatus::PUBLISHED]);

    [$after, $before] = $page->revisions()->get()->all();

    expect($before->content['title'])->toBe(['en' => 'Original'])
        ->and($before->user_id)->toBe($page->updated_by)
        ->and($after->content['title'])->toBe(['en' => 'Renamed'])
        ->and($after->user_id)->toBe($editor->id)
        ->and($after->source)->toBe(RevisionSource::EDITOR)
        ->and($after->authorName())->toBe('Grace Editor');
});

it('records nothing when a save changes no content', function (): void {
    $page = revisedPage();

    resolve(UpdatePageAction::class)->handle($page, ['title' => ['en' => 'Renamed'], 'status' => ContentStatus::PUBLISHED]);
    resolve(UpdatePageAction::class)->handle($page, ['title' => ['en' => 'Renamed'], 'status' => ContentStatus::PUBLISHED]);
    resolve(UpdatePageAction::class)->handle($page, ['status' => ContentStatus::DRAFT]);

    expect($page->revisions()->count())->toBe(2);
});

it('snapshots blocks, slugs, media, categories and layout but not the published languages', function (): void {
    $page = revisedPage();
    $media = Media::factory()->create();
    $category = Category::factory()->create();

    resolve(UpdatePageAction::class)->handle($page, [
        'status' => ContentStatus::PUBLISHED,
        'slugs' => ['en' => 'renamed-page'],
        'blocks' => [['id' => 'new-1', 'type' => 'divider', 'content' => ['size' => 'medium']]],
        'og_image' => ['en' => [['id' => $media->id, 'crop' => ['x' => 1]]]],
        'categories' => [$category->id],
        'metadata' => ['noindex' => true, 'published_locales' => ['en']],
    ]);

    $content = $page->revisions()->firstOrFail()->content;

    expect($content['slugs'])->toBe(['en' => 'renamed-page'])
        ->and($content['blocks'][0])->toMatchArray(['type' => 'divider', 'content' => ['size' => 'medium']])
        ->and($content['media'][0])->toMatchArray(['role' => 'og_image', 'locale' => 'en', 'media_id' => $media->id, 'crop' => ['x' => 1]])
        ->and($content['categories'])->toBe([$category->id])
        ->and($content['metadata'])->toBe(['noindex' => true])
        ->and($content)->not->toHaveKey('data');
});

it('snapshots the field values of a record', function (): void {
    $record = resolve(CreateRecordAction::class)->handle(RecordType::factory()->create(), ['title' => 'Widget']);

    resolve(UpdateRecordAction::class)->handle($record, ['data' => ['sku' => 'W-1'], 'status' => ContentStatus::DRAFT]);

    expect($record->revisions()->count())->toBe(1)
        ->and($record->revisions()->firstOrFail()->content['data'])->toBe(['sku' => 'W-1']);
});

it('keeps only the newest fifty revisions', function (): void {
    $page = revisedPage();
    Revision::factory()->count(Page::REVISIONS_KEPT)->for($page, 'revisionable')->sequence(fn ($sequence): array => ['hash' => 'old-'.$sequence->index])->create();
    $oldest = $page->revisions()->get()->last();

    resolve(UpdatePageAction::class)->handle($page, ['title' => ['en' => 'Fifty-first'], 'status' => ContentStatus::PUBLISHED]);

    expect($page->revisions()->count())->toBe(Page::REVISIONS_KEPT)
        ->and(Revision::query()->whereKey($oldest->id)->exists())->toBeFalse()
        ->and($page->revisions()->firstOrFail()->content['title'])->toBe(['en' => 'Fifty-first']);
});

it('removes the history along with the page', function (): void {
    $page = revisedPage();
    resolve(UpdatePageAction::class)->handle($page, ['title' => ['en' => 'Renamed'], 'status' => ContentStatus::PUBLISHED]);

    resolve(DeletePageAction::class)->handle($page);

    expect(Revision::query()->count())->toBe(0);
});

it('labels changes made through the MCP server as an AI agent', function (): void {
    $page = revisedPage();

    WireUpServer::tool(UpdatePageTool::class, ['page' => $page->id, 'title' => 'Renamed by an agent'])->assertOk();

    $latest = $page->revisions()->firstOrFail();

    expect($latest->source)->toBe(RevisionSource::AGENT)
        ->and($latest->authorName())->toBe('AI agent');
});

it('labels changes made by the in-admin assistant', function (): void {
    $this->actingAsAdmin();
    $page = revisedPage();

    SiteAssistant::fake([new ToolCall('c1', 'update-page', ['page' => $page->id, 'title' => 'Renamed by the assistant']), 'Renamed it.']);

    Livewire::test('admin.assistant')->call('send', 'Rename the page');

    expect($page->revisions()->firstOrFail()->source)->toBe(RevisionSource::ASSISTANT);
});

it('names an editor whose account is gone as someone', function (): void {
    expect(Revision::factory()->make(['user_id' => null])->authorName())->toBe('Someone');
});
