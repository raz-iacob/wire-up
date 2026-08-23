<?php

declare(strict_types=1);

use App\Models\Locale;
use App\Models\Settings;
use App\Services\UiStrings;
use Livewire\Livewire;

beforeEach(function (): void {
    cache()->forget('ui-strings-catalog');
});

function formMessageKeys(): array
{
    $group = collect(UiStrings::catalog())->firstWhere('group', 'Form messages');

    return $group['strings'] ?? [];
}

it('offers the reachable framework messages for translation', function (string $key): void {
    expect(formMessageKeys())->toContain($key);
})->with([
    'validation.required',
    'validation.unique',
    'validation.email',
    'validation.max.string',
    'validation.min.string',
]);

it('already offered the throttle message through the scanned sign-in strings', function (): void {
    expect(formMessageKeys())->not->toContain('auth.throttle')
        ->and(UiStrings::strings())->toContain('auth.throttle');
});

it('leaves out rules the site never uses', function (string $key): void {
    expect(formMessageKeys())->not->toContain($key);
})->with([
    'validation.mimetypes',
    'validation.uuid',
    'validation.custom',
    'validation.attributes',
]);

it('keeps the scanned interface strings alongside them', function (): void {
    $groups = collect(UiStrings::catalog())->pluck('group');

    expect($groups)->toContain('Form messages')
        ->and($groups->count())->toBeGreaterThan(1);
});

it('lets an owner translate a validation message for another language', function (): void {
    Locale::query()->updateOrCreate(['code' => 'nl'], ['name' => 'Nederlands', 'active' => true]);

    Settings::set(['ui_translations' => ['nl' => [
        'validation.required' => 'Het veld :attribute is verplicht.',
    ]]]);

    app()->setLocale('nl');

    expect(trans('validation.required'))->toBe('Het veld :attribute is verplicht.');

    app()->setLocale('en');

    expect(trans('validation.required'))->toBe('The :attribute field is required.');
});

it('shows the owner translation in a real admin form error', function (): void {
    Settings::set(['ui_translations' => ['en' => [
        'validation.required' => 'Please fill in the :attribute.',
    ]]]);

    $this->actingAsAdmin();

    $errors = Livewire::test('pages::admin.categories-index')
        ->set('name', '')
        ->call('create')
        ->errors()
        ->all();

    expect($errors)->toBe(['Please fill in the name.']);
});

it('surfaces the translated throttle message on the sign-in form', function (): void {
    Settings::set(['ui_translations' => ['en' => [
        'auth.throttle' => 'Too many tries. Wait :seconds seconds.',
    ]]]);

    expect(__('auth.throttle', ['seconds' => 30]))->toBe('Too many tries. Wait 30 seconds.');
});
