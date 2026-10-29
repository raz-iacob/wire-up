<?php

declare(strict_types=1);

use App\Actions\UpdateRecordAction;
use App\Actions\UpdateRecordTypeAction;
use App\Enums\ContentStatus;
use App\Enums\MediaType;
use App\Mcp\Servers\WireUpServer;
use App\Mcp\Tools\CreateContentTypeTool;
use App\Mcp\Tools\UpdateContentTypeTool;
use App\Models\Media;
use App\Models\Record;
use App\Models\RecordType;
use App\Services\RecordCollectionQuery;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    Storage::fake(config()->string('filesystems.media'));
    Storage::fake(config()->string('filesystems.private_media'));
});

function clientsType(array $attributes = []): RecordType
{
    return RecordType::factory()->adminOnly()->create([
        'key' => 'clients',
        'slug_prefix' => 'clients',
        'fields' => [
            ['key' => 'stage', 'type' => 'select', 'label' => ['en' => 'Stage'], 'options' => ['Lead', 'Active'], 'filterable' => true],
            ['key' => 'contract', 'type' => 'document', 'label' => ['en' => 'Contract']],
        ],
        ...$attributes,
    ]);
}

function publicDocument(string $source = 'media/contract.pdf'): Media
{
    Storage::disk(config()->string('filesystems.media'))->put($source, 'signed');

    return Media::factory()->create(['type' => MediaType::DOCUMENT, 'source' => $source]);
}

it('keeps admin-only types off the site whatever their page settings say', function (): void {
    $type = RecordType::factory()->create([
        'admin_only' => true,
        'breadcrumbs' => true,
        'has_detail_page' => true,
        'has_index_page' => true,
        'sellable' => true,
    ]);

    expect($type->fresh())
        ->breadcrumbs->toBeFalse()
        ->has_detail_page->toBeFalse()
        ->has_index_page->toBeFalse()
        ->sellable->toBeFalse();
});

it('makes the documents already attached to a type private when it becomes admin-only', function (): void {
    $type = RecordType::factory()->create(['fields' => [['key' => 'contract', 'type' => 'document', 'label' => ['en' => 'Contract']]]]);
    $record = Record::factory()->for($type, 'recordType')->create();
    $document = publicDocument();
    $photo = Media::factory()->create(['type' => MediaType::IMAGE]);
    $record->syncMediaForRole('contract', 'en', [['id' => $document->id]]);
    $record->syncMediaForRole('og_image', 'en', [['id' => $photo->id]]);

    new UpdateRecordTypeAction()->handle($type, ['admin_only' => true]);

    expect($document->fresh()->is_private)->toBeTrue()
        ->and($photo->fresh()->is_private)->toBeFalse();

    Storage::disk(config()->string('filesystems.private_media'))->assertExists('media/contract.pdf');
});

it('makes documents private when a record of an admin-only type is saved', function (): void {
    $record = Record::factory()->for(clientsType(), 'recordType')->create();
    $document = publicDocument();

    new UpdateRecordAction()->handle($record, ['media' => ['contract' => ['en' => [['id' => $document->id]]]]]);

    expect($document->fresh()->is_private)->toBeTrue();
});

it('saves the admin-only switch and drops selling for admin-only types', function (): void {
    $this->actingAsAdmin();
    $type = RecordType::factory()->sellable()->create(['slug_prefix' => 'clients', 'name' => 'Clients']);

    Livewire::test('pages::admin.settings-content-types')
        ->assertSee('Give each record its own page')
        ->set('types.0.admin_only', true)
        ->assertDontSee('Give each record its own page')
        ->call('update')
        ->assertHasNoErrors();

    expect($type->fresh())
        ->admin_only->toBeTrue()
        ->sellable->toBeFalse();
});

it('leaves status, preview and the public link out of an admin-only list', function (): void {
    $this->actingAsAdmin();
    $type = clientsType();
    Record::factory()->for($type, 'recordType')->create(['title' => 'Acme', 'status' => ContentStatus::DRAFT]);

    Livewire::test('pages::admin.records-index', ['recordType' => $type])
        ->set('status', ContentStatus::PUBLISHED->value)
        ->assertSee('Acme')
        ->assertSee('Stage')
        ->assertDontSee('Draft')
        ->assertDontSee(route('record', ['clients', 'acme']));
});

it('hides the filter menu when an admin-only type has nothing to filter by', function (): void {
    $this->actingAsAdmin();
    $type = clientsType(['fields' => []]);

    Livewire::test('pages::admin.records-index', ['recordType' => $type])
        ->assertDontSee('Filter');
});

it('edits an admin-only record without publishing, SEO or page options', function (): void {
    $this->actingAsAdmin();
    $type = clientsType();
    $record = Record::factory()->for($type, 'recordType')->create(['title' => 'Acme']);

    Livewire::test('pages::admin.records-edit', ['recordType' => $type, 'record' => $record])
        ->assertSee('Details')
        ->assertSee('Contract')
        ->assertDontSee('SEO Settings')
        ->assertDontSee('Choose status')
        ->assertDontSee('Web Address')
        ->assertDontSee('Preview');
});

it('never lists admin-only records in collections or linked records', function (): void {
    $type = clientsType();
    $client = Record::factory()->for($type, 'recordType')->create([
        'status' => ContentStatus::PUBLISHED,
        'published_at' => now()->subDay(),
    ]);
    $project = Record::factory()->create(['data' => ['client' => [$client->id]]]);

    expect((new RecordCollectionQuery)->resolve(['recordTypeId' => $type->id, 'source' => 'latest']))->toBeEmpty()
        ->and($project->linkedRecords(['key' => 'client', 'record_type' => 'clients']))->toBeEmpty()
        ->and($project->linkedRecords(['key' => 'client', 'record_type' => 'clients'], publishedOnly: false)->pluck('id')->all())->toBe([$client->id]);
});

it('creates and updates admin-only types over MCP', function (): void {
    WireUpServer::tool(CreateContentTypeTool::class, ['name' => 'Leads', 'admin_only' => true, 'sellable' => true])
        ->assertOk()
        ->assertSee('"admin_only":true')
        ->assertSee('"sellable":false');

    $type = RecordType::factory()->create(['key' => 'partners', 'slug_prefix' => 'partners']);

    WireUpServer::tool(UpdateContentTypeTool::class, ['type' => 'partners', 'admin_only' => true])
        ->assertOk();

    expect($type->fresh()->admin_only)->toBeTrue();
});
