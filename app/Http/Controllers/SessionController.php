<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final readonly class SessionController
{
    public function destroy(Request $request): RedirectResponse
    {
        $returnsToLogin = Auth::user()?->canAccessAdmin() ?? false;

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect($returnsToLogin ? route('login') : route('home'));
    }
}
