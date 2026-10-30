<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Impersonation;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class StopImpersonationController
{
    public function __invoke(#[CurrentUser] User $member): RedirectResponse
    {
        return redirect(Impersonation::active() ? Impersonation::stop($member) : route('account'));
    }
}
