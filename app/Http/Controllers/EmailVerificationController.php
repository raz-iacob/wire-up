<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

final readonly class EmailVerificationController
{
    public function update(EmailVerificationRequest $request, #[CurrentUser] User $user): RedirectResponse
    {
        $routeName = $user->admin ? 'admin.dashboard' : 'home';

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route($routeName, absolute: false).'?verified=1');
        }

        $request->fulfill();

        return redirect()->intended(route($routeName, absolute: false).'?verified=1');
    }
}
