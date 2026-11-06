<?php

declare(strict_types=1);

namespace App\Traits;

use App\Actions\UpdateSettingsAction;
use App\Integrations\CardIntegration;
use App\Services\IntegrationTestResult;
use Flux\Flux;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;

trait ConnectsIntegration
{
    abstract protected function integration(): CardIntegration;

    #[Computed]
    public function connected(): bool
    {
        $site = config('site');

        return $this->integration()->connected(is_array($site) ? $site : []);
    }

    public function hint(string $setting): string
    {
        return $this->integration()->hint($setting, $this->savedSetting($setting));
    }

    public function disconnect(UpdateSettingsAction $action): void
    {
        $this->authorize('settings.edit');

        $fields = $this->integration()->disconnects();

        $action->handle(array_fill_keys($fields, ''));

        $this->form->reset(...array_values(array_intersect($fields, array_keys($this->form->all()))));

        $this->finish(__('Disconnected.'), notify: true);
    }

    protected function finish(string $message, bool $notify = false): void
    {
        if ($notify) {
            $this->dispatch('integrations-updated');
        }

        Flux::modal('integration-'.$this->integration()->key())->close();
        Flux::toast($message, variant: 'success');
    }

    protected function savedSetting(string $key): string
    {
        $value = config('site.'.$key);

        return is_string($value) ? $value : '';
    }

    protected function typedOrSaved(mixed $typed, string $key): string
    {
        $typed = mb_trim((string) $typed);

        return $typed !== '' ? $typed : $this->savedSetting($key);
    }

    protected function allowsAnotherTest(): bool
    {
        $key = 'integration-test:'.auth()->id();

        if (RateLimiter::tooManyAttempts($key, maxAttempts: 5)) {
            Flux::toast(__('That’s a lot of tests. Please wait a moment and try again.'), variant: 'warning');

            return false;
        }

        RateLimiter::hit($key, decaySeconds: 60);

        return true;
    }

    protected function reportTest(IntegrationTestResult $result): void
    {
        Flux::toast($result->message, variant: $result->passed ? 'success' : 'danger');
    }
}
