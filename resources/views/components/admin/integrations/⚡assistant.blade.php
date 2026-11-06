<?php

declare(strict_types=1);

use App\Actions\UpdateSettingsAction;
use App\Integrations\Assistant;
use App\Livewire\Forms\AssistantIntegrationForm;
use App\Services\AiModelCatalog;
use App\Services\IntegrationTester;
use App\Traits\ConnectsIntegration;
use Livewire\Component;

return new class extends Component
{
    use ConnectsIntegration;

    public AssistantIntegrationForm $form;

    public string $modelSearch = '';

    public function mount(): void
    {
        $this->form->ai_provider = array_key_exists((string) config('site.ai_provider'), AiModelCatalog::PROVIDERS) ? config()->string('site.ai_provider') : 'anthropic';
        $this->form->ai_model = is_string(config('site.ai_model')) && config('site.ai_model') !== '' ? config()->string('site.ai_model') : resolve(AiModelCatalog::class)->defaultFor($this->form->ai_provider);
    }

    /**
     * @return array<string, string>
     */
    public function aiModels(): array
    {
        return resolve(AiModelCatalog::class)->options(
            $this->form->ai_provider,
            $this->apiKey(),
            $this->form->ai_model,
        );
    }

    public function updatedFormAiProvider(string $provider): void
    {
        if (array_key_exists($provider, AiModelCatalog::PROVIDERS)) {
            $this->form->ai_model = resolve(AiModelCatalog::class)->defaultFor($provider);
        }
    }

    public function useTypedModel(): void
    {
        $this->form->ai_model = mb_trim($this->modelSearch);
        $this->modelSearch = '';
    }

    public function connect(UpdateSettingsAction $action): void
    {
        $this->authorize('settings.edit');

        $validated = $this->form->validate();

        $action->handle([
            'ai_provider' => $validated['ai_provider'],
            'ai_api_key' => $this->apiKey(),
            'ai_model' => mb_trim($validated['ai_model']),
        ]);

        $this->form->reset('ai_api_key');

        $this->finish(__('AI Assistant connected.'), notify: true);
    }

    public function test(IntegrationTester $tester): void
    {
        $this->authorize('settings.edit');

        $validated = $this->form->validate();

        if (! $this->allowsAnotherTest()) {
            return;
        }

        $this->reportTest($tester->assistant(
            $validated['ai_provider'],
            $this->apiKey(),
            mb_trim($validated['ai_model']),
        ));
    }

    protected function integration(): Assistant
    {
        return new Assistant;
    }

    private function apiKey(): string
    {
        $typed = mb_trim($this->form->ai_api_key);

        if ($typed !== '' || $this->form->ai_provider !== $this->savedSetting('ai_provider')) {
            return $typed;
        }

        return $this->savedSetting('ai_api_key');
    }
};
?>

<div>
    <x-admin.integration-card
        :name="__('AI Assistant')"
        :description="__('Let an AI model build and fill your site from a chat — pages, blocks, design and more.')"
        modal="integration-assistant"
        :connected="$this->connected"
    >
        <x-slot:logo>
            <flux:icon name="sparkles" class="text-accent size-6" />
        </x-slot:logo>
    </x-admin.integration-card>

    <flux:modal name="integration-assistant" class="w-full md:max-w-lg">
        <form wire:submit="connect" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Connect the AI Assistant') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Choose a provider and paste an API key. The assistant can create pages, edit content, adjust the design and manage menus — it never touches users or app integrations.') }}</flux:text>
            </div>

            <flux:select wire:model.live="form.ai_provider" :label="__('Provider')">
                @foreach (\App\Services\AiModelCatalog::PROVIDERS as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input
                wire:model.blur="form.ai_api_key"
                type="password"
                viewable
                :label="__('API key')"
                :placeholder="$this->connected ? $this->hint('ai_api_key') : __('Paste your API key…')"
                :description="$this->connected ? __('Leave blank to keep the saved key.') : null"
            />

            <flux:select
                wire:model="form.ai_model"
                variant="combobox"
                :label="__('Model')"
                :description="__('Paste your key to see the newest models, or type any model name.')"
            >
                <x-slot name="input">
                    <flux:select.input wire:model="modelSearch" />
                </x-slot>
                @foreach ($this->aiModels() as $value => $label)
                    <flux:select.option value="{{ $value }}" wire:key="model-{{ $value }}">
                        {{ $label }}</flux:select.option>
                @endforeach
                <flux:select.option.create wire:click="useTypedModel" min-length="3">
                    {{ __('Use') }} "<span wire:text="modelSearch"></span>"
                </flux:select.option.create>
            </flux:select>

            <x-admin.integration-actions
                :connected="$this->connected"
                :test="__('Sends a short prompt to check the key and model')"
            />
        </form>
    </flux:modal>
</div>
