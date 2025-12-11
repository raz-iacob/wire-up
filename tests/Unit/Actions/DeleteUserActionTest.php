<?php

declare(strict_types=1);

use App\Actions\DeleteUserAction;
use App\Models\User;

it('may delete a user', function (): void {
    $user = User::factory()->create();

    $action = resolve(DeleteUserAction::class);

    $action->handle($user);

    expect($user->exists)->toBeFalse();
});
