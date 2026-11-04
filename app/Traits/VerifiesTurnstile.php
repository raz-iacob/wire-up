<?php

declare(strict_types=1);

namespace App\Traits;

use App\Services\Turnstile;
use Illuminate\Validation\ValidationException;

trait VerifiesTurnstile
{
    public string $turnstileToken = '';

    protected function ensureHuman(string $errorKey = 'turnstile'): void
    {
        $turnstile = resolve(Turnstile::class);

        if (! $turnstile->enabled()) {
            return;
        }

        $passed = $turnstile->verify($this->turnstileToken, request()->ip());

        $this->turnstileToken = '';
        $this->dispatch('turnstile-reset');

        if (! $passed) {
            throw ValidationException::withMessages([$errorKey => __('Please confirm you’re human and try again.')]);
        }
    }
}
