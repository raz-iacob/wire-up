<?php

declare(strict_types=1);

use App\Traits\WithSorting;
use Livewire\Component;

it('has default sort properties', function (): void {
    $component = new class extends Component
    {
        use WithSorting;
    };

    expect($component->sortBy)->toBe('created_at')
        ->and($component->sortDirection)->toBe('desc');
});

it('can sort by new field', function (): void {
    $component = new class extends Component
    {
        use WithSorting;
    };

    $component->sort('name');

    expect($component->sortBy)->toBe('name')
        ->and($component->sortDirection)->toBe('asc');
});

it('toggles direction when sorting by same field', function (): void {
    $component = new class extends Component
    {
        use WithSorting;
    };

    $component->sortBy = 'name';
    $component->sortDirection = 'asc';

    $component->sort('name');

    expect($component->sortBy)->toBe('name')
        ->and($component->sortDirection)->toBe('desc');
});

it('toggles direction back to asc after desc', function (): void {
    $component = new class extends Component
    {
        use WithSorting;
    };

    $component->sortBy = 'name';
    $component->sortDirection = 'desc';

    $component->sort('name');

    expect($component->sortBy)->toBe('name')
        ->and($component->sortDirection)->toBe('asc');
});

it('resets to asc when sorting by different field', function (): void {
    $component = new class extends Component
    {
        use WithSorting;
    };

    $component->sortBy = 'name';
    $component->sortDirection = 'desc';

    $component->sort('email');

    expect($component->sortBy)->toBe('email')
        ->and($component->sortDirection)->toBe('asc');
});

it('can handle multiple field switches', function (): void {
    $component = new class extends Component
    {
        use WithSorting;
    };

    // Start with name asc
    $component->sort('name');
    expect($component->sortBy)->toBe('name')
        ->and($component->sortDirection)->toBe('asc');

    // Toggle to name desc
    $component->sort('name');
    expect($component->sortBy)->toBe('name')
        ->and($component->sortDirection)->toBe('desc');

    // Switch to email asc
    $component->sort('email');
    expect($component->sortBy)->toBe('email')
        ->and($component->sortDirection)->toBe('asc');

    // Back to name asc (resets)
    $component->sort('name');
    expect($component->sortBy)->toBe('name')
        ->and($component->sortDirection)->toBe('asc');
});

it('preserves field when toggling direction multiple times', function (): void {
    $component = new class extends Component
    {
        use WithSorting;
    };

    $component->sort('created_at');

    // First toggle
    $component->sort('created_at');
    expect($component->sortBy)->toBe('created_at')
        ->and($component->sortDirection)->toBe('desc');

    // Second toggle
    $component->sort('created_at');
    expect($component->sortBy)->toBe('created_at')
        ->and($component->sortDirection)->toBe('asc');

    // Third toggle
    $component->sort('created_at');
    expect($component->sortBy)->toBe('created_at')
        ->and($component->sortDirection)->toBe('desc');
});
