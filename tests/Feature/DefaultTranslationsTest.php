<?php

declare(strict_types=1);

use App\Mcp\Servers\WireUpServer;
use App\Mcp\Tools\CreateCategoryTool;
use App\Models\Settings;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;

it('resolves the framework line groups rather than returning raw keys', function (string $key): void {
    expect(trans($key))->not->toBe($key);
})->with([
    'validation.required',
    'validation.unique',
    'validation.min.string',
    'validation.max.string',
    'auth.failed',
    'auth.throttle',
    'passwords.reset',
    'passwords.throttled',
    'pagination.previous',
    'pagination.next',
]);

it('keeps the framework lang directory in the loader paths', function (): void {
    expect(resolve('translation.loader')->paths())
        ->toContain(app()->langPath())
        ->and(collect(resolve('translation.loader')->paths()))
        ->contains(fn (string $path): bool => str_contains($path, 'Illuminate/Translation/lang'))->toBeTrue();
});

it('shows a real sentence when an admin form fails validation', function (): void {
    $this->actingAsAdmin();

    $errors = Livewire::test('pages::admin.categories-index')
        ->set('name', '')
        ->call('create')
        ->errors()
        ->all();

    expect($errors)->toBe(['The name field is required.']);
});

it('shows a real sentence when a console prompt rule fails', function (): void {
    $validator = Validator::make(
        ['email' => 'not-an-email'],
        ['email' => ['required', 'email:rfc', 'max:255']],
    );

    expect($validator->errors()->first('email'))->toBe('The email field must be a valid email address.');
});

it('shows a real sentence when an mcp tool argument fails validation', function (): void {
    WireUpServer::tool(CreateCategoryTool::class, ['name' => str_repeat('y', 300)])
        ->assertHasErrors(['The name field must not be greater than 255 characters.']);
});

it('still lets owner-edited interface strings override free text', function (): void {
    Settings::set(['ui_translations' => ['en' => ['Read more' => 'Keep reading']]]);

    expect(__('Read more'))->toBe('Keep reading')
        ->and(trans('validation.required'))->toBe('The :attribute field is required.');
});
