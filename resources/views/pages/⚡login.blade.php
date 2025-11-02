<?php

use App\Models\User;
use Livewire\Component;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

new class extends Component
{
    public string $layout = 'simple';

    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function mount(): void
    {
        //
    }

    public function login(): void
    {
        $credentials = $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $this->ensureIsNotRateLimited();

        throw_unless(Auth::attemptWhen($credentials, fn (User $user): bool => $user->active, remember: $this->remember), ValidationException::withMessages([
            'email' => __('auth.failed'),
        ]));

        RateLimiter::clear($this->throttleKey());
        Session::regenerate();

        // if(Auth::user()?->admin) {
        //     $this->redirectIntended(default: route('admin.dashboard', absolute: false), navigate: true);
        //     return;
        // }
        $this->redirectIntended(default: route('home', absolute: false), navigate: true);
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email) . '|' . request()->ip());
    }

    public function render(): View
    {
        return $this->view()
            ->layout('layouts::auth.'.$this->layout);
    }
};
?>

<div class="flex flex-col gap-6">
    <div class="flex w-full flex-col text-center">
        <flux:heading size="xl">{{ __('Log in to your account') }}</flux:heading>
        <flux:subheading>{{ __('Enter your email and password below to log in') }}</flux:subheading>
    </div>

    <form method="POST" wire:submit="login" class="flex flex-col gap-6">
        {{-- Email Address --}}
        <flux:input
            wire:model="email"
            :label="__('Email address')"
            type="email"
            required
            autofocus
            autocomplete="email"
            placeholder="email@example.com"
        />

        {{-- Password --}}
        <div class="relative">
            <flux:input
                wire:model="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="current-password"
                :placeholder="__('Password')"
                viewable
            />

            @if (Route::has('password.request'))
                <flux:link class="absolute end-0 top-0 text-sm" :href="route('password.request')" wire:navigate>
                    {{ __('Forgot your password?') }}
                </flux:link>
            @endif
        </div>

        {{-- Remember Me --}}
        <flux:checkbox wire:model="remember" :label="__('Remember me')" />

        <div class="flex items-center justify-end">
            <flux:button variant="primary" type="submit" class="w-full">{{ __('Log in') }}</flux:button>
        </div>
    </form>

    @if (Route::has('register'))
        <div class="space-x-1 text-center text-sm text-zinc-600 rtl:space-x-reverse dark:text-zinc-400">
            <span>{{ __('Don\'t have an account?') }}</span>
            <flux:link :href="route('register')" wire:navigate>{{ __('Sign up') }}</flux:link>
        </div>
    @endif
</div>