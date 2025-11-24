<?php

declare(strict_types=1);

it('can render the page screen', function (): void {
    $response = $this->fromRoute('home')
        ->get(route('page', ['slug' => 'home']));

    $response->assertOk()
        ->assertSeeLivewire('pages::page');
});

it('returns 404 if the page does not exist', function (): void {
    $response = $this->fromRoute('home')
        ->get(route('page', ['slug' => 'non-existent-page']));

    $response->assertNotFound();
});
