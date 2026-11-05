<?php

declare(strict_types=1);

use App\Actions\CreateSubmissionAction;
use App\Jobs\SubscribeToNewsletter;
use App\Services\NewsletterService;
use App\Services\PageCache;
use App\Traits\VerifiesTurnstile;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

return new class extends Component
{
    use VerifiesTurnstile;

    private const int MIN_SECONDS = 3;

    private const int MAX_ATTEMPTS = 5;

    private const int DECAY_SECONDS = 60;

    /**
     * @var array<string, mixed>
     */
    #[Locked]
    public array $config = [];

    #[Locked]
    public ?int $blockId = null;

    #[Locked]
    public ?int $pageId = null;

    #[Locked]
    public int $startedAt = 0;

    public string $name = '';

    public string $email = '';

    public string $website = '';

    public bool $subscribed = false;

    public function mount(): void
    {
        resolve(PageCache::class)->skip();

        $this->startedAt = now()->timestamp;
    }

    public function subscribe(CreateSubmissionAction $action, NewsletterService $newsletter): void
    {
        if ($this->website !== '' || now()->timestamp - $this->startedAt < self::MIN_SECONDS) {
            $this->addError('form', __('Sorry, we couldn’t sign you up. Please try again.'));

            return;
        }

        $throttleKey = 'newsletter:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $this->addError('form', __('Too many attempts. Please wait a moment and try again.'));

            return;
        }

        RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

        $this->ensureHuman('form');

        $this->name = mb_trim($this->name);
        $this->email = mb_trim($this->email);

        $this->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
        ], [], [
            'name' => __('First name'),
            'email' => __('Email'),
        ]);

        $provider = $newsletter->provider();

        $submission = $action->handle([
            'page_id' => $this->pageId,
            'block_id' => $this->blockId,
            'type' => 'newsletter',
            'form_name' => 'Newsletter sign-up',
            'name' => $this->asksForName() && $this->name !== '' ? $this->name : null,
            'email' => $this->email,
            'metadata' => $provider === null ? null : ['newsletter' => ['provider' => $provider, 'status' => 'pending', 'error' => null, 'at' => now()->toIso8601String()]],
            'ip' => request()->ip(),
            'locale' => app()->getLocale(),
            'country' => request()->header('CF-IPCOUNTRY'),
        ]);

        if ($provider !== null) {
            dispatch(new SubscribeToNewsletter($submission->id))->afterCommit();
        }

        $this->reset(['name', 'email', 'website']);
        $this->subscribed = true;
    }

    #[Computed]
    public function asksForName(): bool
    {
        return data_get($this->config, 'askName') === true;
    }

    #[Computed]
    public function layout(): string
    {
        return data_get($this->config, 'layout') === 'stacked' ? 'stacked' : 'inline';
    }

    #[Computed]
    public function centered(): bool
    {
        return data_get($this->config, 'align', 'center') !== 'left';
    }

    /**
     * @return array{heading: string, intro: string, namePlaceholder: string, emailPlaceholder: string, buttonText: string, successMessage: string, privacyNote: string}
     */
    #[Computed]
    public function copy(): array
    {
        return [
            'heading' => $this->text('heading'),
            'intro' => $this->text('intro'),
            'namePlaceholder' => $this->text('namePlaceholder', __('First name')),
            'emailPlaceholder' => $this->text('emailPlaceholder', __('Your email')),
            'buttonText' => $this->text('buttonText', __('Subscribe')),
            'successMessage' => $this->text('successMessage', e(__('Thanks for signing up!'))),
            'privacyNote' => $this->text('privacyNote'),
        ];
    }

    public function render(): View
    {
        return $this->view();
    }

    private function text(string $key, string $default = ''): string
    {
        $map = data_get($this->config, $key);
        $map = is_array($map) ? $map : [];
        $value = $map[app()->getLocale()] ?? $map[config()->string('app.default_locale', 'en')] ?? '';

        return is_string($value) && strip_tags($value) !== '' ? $value : $default;
    }
};
?>

@php
    $inputClass = 'wire-field w-full min-w-0 rounded-(--wire-btn-radius) bg-(--wire-input-bg) px-4 py-3 text-base text-(--wire-input-text) focus:outline-none';
    $errorClass = 'inline-block rounded-(--wire-radius) bg-red-100 px-2 py-0.5 text-sm font-medium text-red-700';
    $headingLevel = \App\Services\BlockHeading::level($this->config['headingLevel'] ?? null);
    $copy = $this->copy;
    $inline = $this->layout === 'inline';
    $fieldId = 'newsletter-'.$this->getId();
@endphp

<div @class([
    'max-w-2xl' => $inline,
    'max-w-md' => ! $inline,
    'mx-auto text-center' => $this->centered,
])>
    @if ($copy['heading'] !== '')
        <div class="[&>p]:m-0 [&_a]:text-(--wire-accent) [&_a]:underline mb-4 text-(length:--wire-heading-size) tracking-tight text-(--wire-heading)">
            <x-site.blocks.heading :html="$copy['heading']" :level="$headingLevel" />
        </div>
    @endif

    @if ($copy['intro'] !== '')
        <div class="[&>p]:m-0 [&_a]:text-(--wire-accent) [&_a]:underline mb-6 leading-(--wire-body-leading)">
            {!! $copy['intro'] !!}
        </div>
    @endif

    @if ($subscribed)
        <div
            class="wire-field [&>p]:m-0 [&_a]:text-(--wire-accent) [&_a]:underline rounded-(--wire-radius) bg-(--wire-input-bg) p-4 leading-(--wire-body-leading) text-(--wire-input-text)"
            data-newsletter-success
        >
            {!! $copy['successMessage'] !!}
        </div>
    @else
        <form wire:submit="subscribe" novalidate class="flex flex-col gap-3">
            <div @class(['flex gap-3', 'flex-col sm:flex-row' => $inline, 'flex-col' => ! $inline])>
                @if ($this->asksForName)
                    <label for="{{ $fieldId }}-name" class="sr-only">{{ __('First name') }}</label>
                    <input
                        id="{{ $fieldId }}-name"
                        type="text"
                        wire:model="name"
                        autocomplete="given-name"
                        placeholder="{{ $copy['namePlaceholder'] }}"
                        class="{{ $inputClass }} {{ $inline ? 'sm:w-2/5' : '' }}"
                    />
                @endif
                <label for="{{ $fieldId }}-email" class="sr-only">{{ __('Email') }}</label>
                <input
                    id="{{ $fieldId }}-email"
                    type="email"
                    wire:model="email"
                    autocomplete="email"
                    required
                    placeholder="{{ $copy['emailPlaceholder'] }}"
                    class="{{ $inputClass }}"
                />
                <button
                    type="submit"
                    class="wire-btn inline-flex shrink-0 items-center justify-center rounded-(--wire-btn-radius) bg-(--wire-primary-bg) px-6 py-3 text-base font-medium text-(--wire-primary-text) transition [--wire-btn-border:var(--wire-primary-border)] hover:opacity-90 disabled:opacity-50"
                    wire:loading.attr="disabled"
                    wire:target="subscribe"
                >
                    <span wire:loading.remove wire:target="subscribe">{{ $copy['buttonText'] }}</span>
                    <span wire:loading wire:target="subscribe">{{ __('Sending…') }}</span>
                </button>
            </div>

            @error('name')
                <div><span class="{{ $errorClass }}">{{ $message }}</span></div>
            @enderror
            @error('email')
                <div><span class="{{ $errorClass }}">{{ $message }}</span></div>
            @enderror

            <div class="hidden" aria-hidden="true">
                <label
                    >{{ __('Leave this field empty') }}
                    <input
                        type="text"
                        wire:model="website"
                        tabindex="-1"
                        autocomplete="off"
                        data-1p-ignore
                        data-lpignore="true"
                        data-form-type="other"
                    />
                </label>
            </div>

            <x-site.turnstile error="form" />

            @error('form')
                <div class="rounded-(--wire-radius) bg-red-100 px-3 py-2 text-sm font-medium text-red-700">
                    {{ $message }}
                </div>
            @enderror
        </form>

        @if ($copy['privacyNote'] !== '')
            <div class="[&>p]:m-0 [&_a]:underline mt-3 text-sm leading-(--wire-body-leading) opacity-75">
                {!! $copy['privacyNote'] !!}
            </div>
        @endif
    @endif
</div>
