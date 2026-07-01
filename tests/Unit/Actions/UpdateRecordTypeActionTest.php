<?php

declare(strict_types=1);

use App\Actions\UpdateRecordTypeAction;
use App\Models\RecordType;

it('updates a record type', function (): void {
    $type = RecordType::factory()->create([
        'name' => 'Old',
        'icon' => 'rectangle-stack',
    ]);

    resolve(UpdateRecordTypeAction::class)->handle($type, [
        'name' => 'New',
        'icon' => 'star',
    ]);

    $fresh = $type->fresh();

    expect($fresh->name)->toBe('New')
        ->and($fresh->icon)->toBe('star');
});
