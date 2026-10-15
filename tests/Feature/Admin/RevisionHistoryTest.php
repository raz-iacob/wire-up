<?php

declare(strict_types=1);

use App\Actions\CreateRecordAction;
use App\Actions\UpdatePageAction;
use App\Actions\UpdateRecordAction;
use App\Enums\ContentStatus;
use App\Enums\RevisionSource;
use App\Models\Category;
use App\Models\Media;
use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use App\Models\Revision;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;

function historyPage(): Page
{
    $page = Page::factory()->create(['title' => 'First draft', 'status' => ContentStatus::PUBLISHED, 'published_at' => now()->subDay()]);
    $page->slugs()->create(['locale' => 'en', 'slug' => 'first-draft']);

    return Page::query()->findOrFail($page->id);
}

it('lists the saved versions with who made them once the history is opened', function (): void {
    $this->actingAsAdmin();
    $page = historyPage();
    resolve(UpdatePageAction::class)->handle($page, ['title' => ['en' => 'Second draft'], 'status' => ContentStatus::PUBLISHED]);
    Revision::factory()->for($page, 'revisionable')->create(['source' => RevisionSource::ASSISTANT, 'hash' => 'ai', 'created_at' => now()->subHour()]);

    Livewire::test('pages::admin.pages-edit', ['page' => $page])
        ->assertDontSee('AI assistant')
        ->call('openHistory')
        ->assertSet('showHistory', true)
        ->assertSee('Current')
        ->assertSee('AI assistant')
        ->assertSee('Restore');
});

it('says when a page has no saved versions yet', function (): void {
    $this->actingAsAdmin();

    Livewire::test('pages::admin.pages-edit', ['page' => historyPage()])
        ->call('openHistory')
        ->assertSee('No earlier versions yet.');
});

it('sends the editor to an older version and refuses one from another page', function (): void {
    $this->actingAsAdmin();
    $page = historyPage();
    resolve(UpdatePageAction::class)->handle($page, ['title' => ['en' => 'Second draft'], 'status' => ContentStatus::PUBLISHED]);
    $older = $page->revisions()->get()->last();
    $foreign = Revision::factory()->create();

    Livewire::test('pages::admin.pages-edit', ['page' => $page])
        ->call('restoreRevision', $older->id)
        ->assertRedirect(route('admin.pages-edit', ['page' => $page->id, 'restore' => $older->id]));

    Livewire::test('pages::admin.pages-edit', ['page' => $page])
        ->call('restoreRevision', $foreign->id)
        ->assertNotFound();
});

it('loads an older version into the page editor without saving it', function (): void {
    $this->actingAsAdmin();
    $page = historyPage();
    $media = Media::factory()->create();
    $gone = Media::factory()->create();
    $category = Category::factory()->create();
    $deletedCategory = Category::factory()->create();

    resolve(UpdatePageAction::class)->handle($page, [
        'status' => ContentStatus::PUBLISHED,
        'blocks' => [
            ['id' => 'new-1', 'type' => 'divider', 'content' => ['size' => 'large']],
            ['id' => 'new-2', 'type' => 'spacer', 'content' => []],
        ],
        'og_image' => ['en' => [['id' => $media->id], ['id' => $gone->id]]],
        'categories' => [$category->id, $deletedCategory->id],
        'metadata' => ['noindex' => true, 'layout' => ['backgroundImage' => ['id' => $gone->id, 'source' => 'gone.jpg']]],
    ]);
    $older = $page->revisions()->firstOrFail();
    $keptBlock = $page->blocks()->where('type', 'divider')->firstOrFail();

    resolve(UpdatePageAction::class)->handle($page, [
        'title' => ['en' => 'Rewritten'],
        'status' => ContentStatus::PUBLISHED,
        'blocks' => [['id' => (string) $keptBlock->id, 'type' => 'divider', 'content' => ['size' => 'small']]],
        'og_image' => ['en' => []],
        'categories' => [],
        'metadata' => [],
    ]);
    $gone->delete();
    $deletedCategory->delete();

    $component = Livewire::withQueryParams(['restore' => $older->id])
        ->test('pages::admin.pages-edit', ['page' => $page])
        ->assertSet('title.en', 'First draft')
        ->assertSet('noindex', true)
        ->assertSet('layout.backgroundImage', null)
        ->assertSet('categories', [(string) $category->id])
        ->assertSee('Showing the version from');

    $blocks = array_values($component->get('blocks'));

    expect($blocks[0]['id'])->toBe((string) $keptBlock->id)
        ->and($blocks[0]['content']['size'])->toBe('large')
        ->and($blocks[1]['id'])->toStartWith('new-')
        ->and(array_column($component->get('og_image')['en'], 'id'))->toBe([$media->id])
        ->and($page->refresh()->title)->toBe('Rewritten');
});

it('saves a restored version as a new revision and leaves restore mode', function (): void {
    $this->actingAsAdmin();
    $page = historyPage();
    resolve(UpdatePageAction::class)->handle($page, ['title' => ['en' => 'Second draft'], 'status' => ContentStatus::PUBLISHED]);
    $older = $page->revisions()->get()->last();

    Livewire::withQueryParams(['restore' => $older->id])
        ->test('pages::admin.pages-edit', ['page' => $page])
        ->call('update')
        ->assertHasNoErrors()
        ->assertSet('restoring', null)
        ->assertSet('restoredFrom', null);

    expect($page->refresh()->title)->toBe('First draft')
        ->and($page->revisions()->count())->toBe(3);
});

it('ignores a restore link for a version that is not there', function (): void {
    $this->actingAsAdmin();

    Livewire::withQueryParams(['restore' => 999999])
        ->test('pages::admin.pages-edit', ['page' => historyPage()])
        ->assertSet('restoring', null)
        ->assertSet('title.en', 'First draft')
        ->assertDontSee('Showing the version from');
});

it('restores a record version without fields that were removed or its stock', function (): void {
    $this->actingAsAdmin();
    $type = RecordType::factory()->create([
        'fields' => [
            ['key' => 'sku', 'type' => 'text', 'label' => ['en' => 'SKU'], 'required' => false, 'translatable' => false],
            ['key' => 'photo', 'type' => 'photo', 'label' => ['en' => 'Photo'], 'required' => false, 'translatable' => false],
        ],
    ]);
    $media = Media::factory()->create();
    $record = resolve(CreateRecordAction::class)->handle($type, ['title' => 'Widget']);
    $record = Record::query()->findOrFail($record->id);

    resolve(UpdateRecordAction::class)->handle($record, [
        'status' => ContentStatus::DRAFT,
        'data' => ['sku' => 'OLD-1', 'retired' => 'gone'],
        'media' => ['photo' => ['en' => [['id' => $media->id]]]],
    ]);
    $older = $record->revisions()->firstOrFail();
    resolve(UpdateRecordAction::class)->handle($record, ['status' => ContentStatus::DRAFT, 'data' => ['sku' => 'NEW-2'], 'media' => ['photo' => ['en' => []]]]);
    $record->forceFill(['stock' => 7])->save();

    $component = Livewire::withQueryParams(['restore' => $older->id])
        ->test('pages::admin.records-edit', ['recordType' => $type, 'record' => $record])
        ->assertSet('data', ['sku' => 'OLD-1'])
        ->assertSet('stock', '7');

    expect(array_column($component->get('media')['photo']['en'], 'id'))->toBe([$media->id]);

    $component->call('update')->assertHasNoErrors()->assertSet('restoring', null);

    expect($record->refresh()->data['sku'])->toBe('OLD-1');
});

it('keeps the history away from staff who cannot edit the page', function (): void {
    $page = historyPage();
    $role = Role::factory()->create(['abilities' => ['pages.view', 'pages.edit']]);
    $editor = User::factory()->role($role)->create(['active' => true]);
    $this->actingAs($editor);

    $component = Livewire::test('pages::admin.pages-edit', ['page' => $page]);

    $role->update(['abilities' => ['pages.view']]);
    $this->actingAs($editor->fresh());

    $component->call('openHistory')->assertForbidden();
});
