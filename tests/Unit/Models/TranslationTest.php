<?php

declare(strict_types=1);

use App\Models\Page;
use App\Models\Translation;
use Illuminate\Database\QueryException;

test('to array', function (): void {
    $translation = Translation::factory()->create()->refresh();

    expect(array_keys($translation->toArray()))
        ->toBe([
            'id',
            'key',
            'body',
            'locale',
            'translatable_type',
            'translatable_id',
            'created_at',
            'updated_at',
        ]);
});

it('has morph relation to translatable', function (): void {
    $page = Page::factory()->create();

    $translation = Translation::factory()->create([
        'locale' => 'de',
        'key' => 'title',
        'body' => 'Test body',
        'translatable_id' => $page->id,
        'translatable_type' => $page->getMorphClass(),
    ]);

    expect($translation->translatable)->toBeInstanceOf(Page::class)
        ->and($translation->translatable->id)->toBe($page->id);
});

it('requires unique key/locale/translatable', function (): void {
    $page = Page::factory()->create();
    Translation::factory()->create([
        'locale' => 'de',
        'key' => 'title',
        'body' => 'Test body',
        'translatable_id' => $page->id,
        'translatable_type' => $page->getMorphClass(),
    ]);

    expect(fn () => Translation::factory()->create([
        'locale' => 'de',
        'key' => 'title',
        'translatable_id' => $page->id,
        'translatable_type' => $page->getMorphClass(),
    ]))->toThrow(QueryException::class);
});

it('can create translations for different translatable models', function (): void {
    $page = Page::factory()->create();
    $anotherPage = Page::factory()->create();

    $translation1 = Translation::factory()->create([
        'locale' => 'fr',
        'key' => 'title',
        'body' => 'Titre de la page',
        'translatable_id' => $page->id,
        'translatable_type' => $page->getMorphClass(),
    ]);

    $translation2 = Translation::factory()->create([
        'locale' => 'fr',
        'key' => 'title',
        'body' => 'Un autre titre de page',
        'translatable_id' => $anotherPage->id,
        'translatable_type' => $anotherPage->getMorphClass(),
    ]);

    expect($translation1->translatable->id)->toBe($page->id)
        ->and($translation2->translatable->id)->toBe($anotherPage->id);
});

it('can create translations for different locales and keys', function (): void {
    $page = Page::factory()->create();

    $translationFr = Translation::factory()->create([
        'locale' => 'fr',
        'key' => 'title',
        'body' => 'Titre de la page',
        'translatable_id' => $page->id,
        'translatable_type' => $page->getMorphClass(),
    ]);

    $translationDe = Translation::factory()->create([
        'locale' => 'de',
        'key' => 'title',
        'body' => 'Seitentitel',
        'translatable_id' => $page->id,
        'translatable_type' => $page->getMorphClass(),
    ]);

    expect($translationFr->body)->toBe('Titre de la page')
        ->and($translationDe->body)->toBe('Seitentitel');
});
