<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Impersonation;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final readonly class SessionController
{
    public function destroy(Request $request, #[CurrentUser] User $user): RedirectResponse
    {
        if (Impersonation::active()) {
            return redirect(Impersonation::stop($user));
        }

        $returnsToLogin = $user->canAccessAdmin();

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect($returnsToLogin ? route('login') : route('home'));
    }
}
