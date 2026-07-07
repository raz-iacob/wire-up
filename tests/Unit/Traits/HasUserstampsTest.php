<?php

declare(strict_types=1);

use App\Models\Page;
use App\Models\Record;
use App\Models\RecordType;
use App\Models\User;

it('stamps the creating and updating user', function (): void {
    $creator = User::factory()->create();
    $editor = User::factory()->create();
    $type = RecordType::factory()->create(['fields' => []]);

    $this->actingAs($creator);
    $record = Record::factory()->create(['record_type_id' => $type->id]);

    expect($record->created_by)->toBe($creator->id)
        ->and($record->updated_by)->toBe($creator->id);

    $this->actingAs($editor);
    $record->update(['data' => ['x' => 1]]);

    $fresh = $record->fresh();

    expect($fresh->created_by)->toBe($creator->id)
        ->and($fresh->updated_by)->toBe($editor->id);
});

it('exposes creator and editor relations', function (): void {
    $user = User::factory()->create();
    $type = RecordType::factory()->create(['fields' => []]);

    $this->actingAs($user);
    $record = Record::factory()->create(['record_type_id' => $type->id]);
    $record->load('creator', 'editor');

    expect($record->creator?->is($user))->toBeTrue()
        ->and($record->editor?->is($user))->toBeTrue();
});

it('leaves stamps untouched when userstamping is stopped', function (): void {
    $user = User::factory()->create();
    $type = RecordType::factory()->create(['fields' => []]);

    $this->actingAs($user);

    $record = new Record(['record_type_id' => $type->id]);
    $record->stopUserstamping();
    $record->save();

    expect($record->created_by)->toBeNull()
        ->and($record->updated_by)->toBeNull();
});

it('leaves stamps null when no user is authenticated', function (): void {
    $type = RecordType::factory()->create(['fields' => []]);
    $record = Record::factory()->create(['record_type_id' => $type->id]);

    expect($record->created_by)->toBeNull()
        ->and($record->updated_by)->toBeNull();
});

it('stamps pages as well as records', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);
    $page = Page::factory()->create();

    expect($page->created_by)->toBe($user->id)
        ->and($page->updated_by)->toBe($user->id);
});

it('keeps updated_by unchanged when userstamping is stopped before an update', function (): void {
    $creator = User::factory()->create();
    $other = User::factory()->create();
    $type = RecordType::factory()->create(['fields' => []]);

    $this->actingAs($creator);
    $record = Record::factory()->create(['record_type_id' => $type->id]);

    $this->actingAs($other);
    $record->stopUserstamping();
    $record->update(['data' => ['x' => 1]]);

    expect($record->fresh()->updated_by)->toBe($creator->id);
});

it('resumes stamping after startUserstamping is called', function (): void {
    $user = User::factory()->create();
    $type = RecordType::factory()->create(['fields' => []]);

    $this->actingAs($user);

    $record = new Record(['record_type_id' => $type->id]);
    $record->stopUserstamping();
    $record->startUserstamping();
    $record->save();

    expect($record->created_by)->toBe($user->id);
});
