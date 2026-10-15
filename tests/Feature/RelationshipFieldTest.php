<?php

declare(strict_types=1);

use App\Actions\CreateRecordAction;
use App\Actions\DeleteRecordAction;
use App\Actions\DeleteRecordTypeAction;
use App\Enums\ContentStatus;
use App\Mcp\Servers\WireUpServer;
use App\Mcp\Tools\CreateContentTypeTool;
use App\Mcp\Tools\CreateRecordTool;
use App\Mcp\Tools\UpdateContentTypeTool;
use App\Models\Record;
use App\Models\RecordType;
use App\Services\ContentMarkdown;
use Livewire\Livewire;

/**
 * @return array<string, mixed>
 */
function linkField(string $target = 'speaker', bool $multiple = true): array
{
    return ['key' => 'speakers', 'type' => 'relationship', 'label' => ['en' => 'Speakers'], 'required' => false, 'translatable' => false, 'column' => true, 'record_type' => $target, 'multiple' => $multiple];
}

function speakerType(bool $detailPage = true): RecordType
{
    return RecordType::factory()->create(['key' => 'speaker', 'slug_prefix' => 'speakers', 'name' => 'Speakers', 'has_detail_page' => $detailPage, 'fields' => []]);
}

function eventType(bool $multiple = true): RecordType
{
    return RecordType::factory()->create(['key' => 'event', 'slug_prefix' => 'events', 'name' => 'Events', 'has_detail_page' => true, 'fields' => [linkField(multiple: $multiple)]]);
}

function linkedRecord(RecordType $type, string $title, bool $published = true): Record
{
    $record = resolve(CreateRecordAction::class)->handle($type, ['title' => $title]);

    if ($published) {
        $record->update(['status' => ContentStatus::PUBLISHED, 'published_at' => now()->subDay()]);
    }

    return Record::query()->findOrFail($record->id);
}

it('links records from the record editor and stores their ids in order', function (): void {
    $this->actingAsAdmin();
    $speakers = speakerType();
    $events = eventType();
    $ada = linkedRecord($speakers, 'Ada');
    $grace = linkedRecord($speakers, 'Grace');
    $event = linkedRecord($events, 'Launch');

    Livewire::test('pages::admin.records-edit', ['recordType' => $events, 'record' => $event])
        ->assertSee('Select Speakers')
        ->set('data.speakers', [(string) $grace->id, (string) $ada->id, (string) $grace->id])
        ->call('update')
        ->assertHasNoErrors();

    expect($event->refresh()->data['speakers'])->toBe([$grace->id, $ada->id]);
});

it('refuses records of another type and more than one link when only one is allowed', function (): void {
    $this->actingAsAdmin();
    $speakers = speakerType();
    $events = eventType(multiple: false);
    $event = linkedRecord($events, 'Launch');
    $wrong = linkedRecord($events, 'Other event');

    Livewire::test('pages::admin.records-edit', ['recordType' => $events, 'record' => $event])
        ->set('data.speakers', [(string) $wrong->id])
        ->call('update')
        ->assertHasErrors(['data.speakers.0'])
        ->set('data.speakers', [(string) linkedRecord($speakers, 'Ada')->id, (string) linkedRecord($speakers, 'Grace')->id])
        ->call('update')
        ->assertHasErrors(['data.speakers']);
});

it('asks for a target before a linked records field can be filled', function (): void {
    $this->actingAsAdmin();
    $events = RecordType::factory()->create(['key' => 'event', 'slug_prefix' => 'events', 'fields' => [linkField('missing')]]);

    Livewire::test('pages::admin.records-edit', ['recordType' => $events, 'record' => linkedRecord($events, 'Launch')])
        ->assertSee('Choose which content type this field links to under Settings → Content types.');
});

it('lists linked titles in the admin, but only published ones on cards, pages and markdown', function (): void {
    $speakers = speakerType();
    $events = eventType();
    $ada = linkedRecord($speakers, 'Ada');
    $draft = linkedRecord($speakers, 'Secret Draft', published: false);
    $event = linkedRecord($events, 'Launch');
    $event->update(['data' => ['speakers' => [$ada->id, $draft->id]]]);
    $event->refresh();

    expect(linkedRecord($events, 'No speakers yet')->columnValue(linkField()))->toBe('—')
        ->and($event->columnValue(linkField()))->toBe('Ada, Secret Draft')
        ->and($event->columnValue(linkField(), publishedOnly: true))->toBe('Ada')
        ->and(ContentMarkdown::current()->render($event))->toContain('**Speakers:** Ada')
        ->not->toContain('Secret Draft');

    $this->get($event->getUrl())
        ->assertOk()
        ->assertSee('Speakers')
        ->assertSee($ada->getUrl(), false)
        ->assertDontSee('Secret Draft');
});

it('shows a linked title without a link when its type has no detail pages', function (): void {
    $speakers = speakerType(detailPage: false);
    $ada = linkedRecord($speakers, 'Ada');
    $event = linkedRecord(eventType(), 'Launch');
    $event->update(['data' => ['speakers' => [$ada->id]]]);

    $this->get($event->refresh()->getUrl())
        ->assertOk()
        ->assertSee('Ada')
        ->assertDontSee('href="'.url('speakers'), false);
});

it('removes a deleted record from every field that links to it', function (): void {
    $speakers = speakerType();
    $ada = linkedRecord($speakers, 'Ada');
    $grace = linkedRecord($speakers, 'Grace');
    $events = eventType();
    $both = linkedRecord($events, 'Both');
    $only = linkedRecord($events, 'Only Ada');
    $both->update(['data' => ['speakers' => [$ada->id, $grace->id]]]);
    $only->update(['data' => ['speakers' => [$ada->id], 'notes' => 'kept']]);

    resolve(DeleteRecordAction::class)->handle($ada);

    expect($both->refresh()->data['speakers'])->toBe([$grace->id])
        ->and($only->refresh()->data)->toBe(['speakers' => null, 'notes' => 'kept']);
});

it('keeps a content type that other types link to, but lets a type that only links to itself go', function (): void {
    $speakers = speakerType();
    eventType();

    expect(fn () => resolve(DeleteRecordTypeAction::class)->handle($speakers))
        ->toThrow(RuntimeException::class, 'Cannot delete a content type that other content types link to.');

    $self = RecordType::factory()->create(['key' => 'speaker_self', 'slug_prefix' => 'selves', 'fields' => [linkField('speaker_self')]]);

    resolve(DeleteRecordTypeAction::class)->handle($self);

    expect(RecordType::query()->whereKey($self->id)->exists())->toBeFalse();
});

it('sets up linked records fields and warns before deleting a linked type from the content types screen', function (): void {
    $this->actingAsAdmin();
    $speakers = speakerType();
    $events = RecordType::factory()->create(['key' => 'event', 'slug_prefix' => 'events', 'name' => 'Events', 'fields' => []]);

    $component = Livewire::test('pages::admin.settings-content-types');
    $index = collect($component->get('types'))->search(fn (array $type): bool => $type['key'] === 'event');

    $component->call('addField', $component->get("types.{$index}._key"), 'relationship')
        ->set("types.{$index}.fields.0.label", 'Speakers')
        ->call('update')
        ->assertHasErrors(["types.{$index}.fields.0.record_type"])
        ->set("types.{$index}.fields.0.record_type", 'speaker')
        ->set("types.{$index}.fields.0.multiple", true)
        ->call('update')
        ->assertHasNoErrors();

    expect($events->refresh()->fields[0])->toMatchArray(['type' => 'relationship', 'record_type' => 'speaker', 'multiple' => true]);

    $speakerIndex = collect($component->get('types'))->search(fn (array $type): bool => $type['key'] === 'speaker');

    $component->call('confirmRemove', $component->get("types.{$speakerIndex}._key"))
        ->assertSee('Other content types link to it.')
        ->call('removeType');

    expect(RecordType::query()->whereKey($speakers->id)->exists())->toBeTrue();
});

it('lets agents create linked records fields and fill them with ids of the right type', function (): void {
    $speakers = speakerType();
    $ada = linkedRecord($speakers, 'Ada');
    $grace = linkedRecord($speakers, 'Grace');

    WireUpServer::tool(CreateContentTypeTool::class, [
        'name' => 'Talks',
        'fields' => [['key' => 'speakers', 'type' => 'relationship', 'label' => 'Speakers']],
    ])->assertHasErrors(['The "speakers" field links records, so give it a "record_type"']);

    WireUpServer::tool(CreateContentTypeTool::class, [
        'name' => 'Talks',
        'fields' => [['key' => 'speakers', 'type' => 'relationship', 'label' => 'Speakers', 'record_type' => 'speaker', 'multiple' => false]],
    ])->assertOk()->assertSee('"record_type":"speaker"');

    $talks = RecordType::query()->where('key', 'talks')->firstOrFail();

    WireUpServer::tool(UpdateContentTypeTool::class, [
        'type' => 'talks',
        'fields' => [['key' => 'speakers', 'type' => 'relationship', 'label' => 'Speakers', 'record_type' => 'nowhere']],
    ])->assertHasErrors(['give it a "record_type"']);

    WireUpServer::tool(CreateRecordTool::class, [
        'type' => 'talks',
        'title' => 'Keynote',
        'data' => ['speakers' => [$grace->id, 999999, $ada->id]],
    ])->assertOk();

    expect(Record::query()->where('record_type_id', $talks->id)->firstOrFail()->data['speakers'])->toBe([$grace->id]);
});
