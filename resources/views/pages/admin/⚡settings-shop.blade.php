<?php

declare(strict_types=1);

use App\Actions\UpdateSettingsAction;
use App\Services\ShopService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

return new class extends Component
{
    /**
     * @var array<int, string>
     */
    public array $countries = [];

    /**
     * @var array<int, array{name: string, amount: string}>
     */
    public array $rates = [];

    public bool $automaticTax = false;

    public bool $pricesIncludeTax = false;

    public function mount(ShopService $shop): void
    {
        $this->countries = $shop->shippingCountries();
        $this->rates = $shop->shippingRates();
        $this->automaticTax = $shop->calculatesTax();
        $this->pricesIncludeTax = $shop->pricesIncludeTax();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function countryOptions(): array
    {
        return resolve(ShopService::class)->countryOptions();
    }

    public function addRate(): void
    {
        if (count($this->rates) < ShopService::MAX_SHIPPING_RATES) {
            $this->rates[] = ['name' => '', 'amount' => ''];
        }
    }

    public function removeRate(int $index): void
    {
        unset($this->rates[$index]);

        $this->rates = array_values($this->rates);
    }

    public function update(UpdateSettingsAction $action): void
    {
        $this->authorize('settings.edit');

        $validated = $this->validate([
            'countries' => ['array'],
            'countries.*' => ['string', Rule::in(array_keys($this->countryOptions()))],
            'rates' => ['array', 'max:'.ShopService::MAX_SHIPPING_RATES],
            'rates.*.name' => ['required', 'string', 'max:100'],
            'rates.*.amount' => ['required', 'numeric', 'min:0', 'max:100000'],
            'automaticTax' => ['boolean'],
            'pricesIncludeTax' => ['boolean'],
        ], attributes: [
            'countries.*' => __('country'),
            'rates.*.name' => __('rate name'),
            'rates.*.amount' => __('rate price'),
        ]);

        $action->handle([
            'shop_shipping_countries' => array_values($validated['countries'] ?? []),
            'shop_shipping_rates' => array_map(fn (array $rate): array => [
                'name' => mb_trim((string) $rate['name']),
                'amount' => (string) $rate['amount'],
            ], array_values($validated['rates'] ?? [])),
            'shop_automatic_tax' => (bool) $validated['automaticTax'],
            'shop_prices_include_tax' => (bool) $validated['pricesIncludeTax'],
        ]);

        Flux::toast(__('Shop settings have been updated.'), variant: 'success');
    }

    public function render(): View
    {
        return $this->view()
            ->title(__('Shop'))
            ->layout('layouts::admin');
    }
};
?>

<x-admin.settings-layout>
    <form
        wire:submit="update"
        wire:warn-dirty="{{ __('Leaving? Changes you made may not be saved.') }}"
        class="grid items-start gap-10 md:grid-cols-5"
    >
        <div class="space-y-10 md:col-span-3">
            <div class="space-y-6">
                <flux:heading size="lg">{{ __('Shipping') }}</flux:heading>

                <flux:pillbox
                    wire:model="countries"
                    searchable
                    :label="__('Ship to')"
                    :description="__('Items that need shipping can only be bought from these countries.')"
                    :placeholder="__('Choose countries…')"
                >
                    @foreach ($this->countryOptions as $code => $country)
                        <flux:pillbox.option :value="$code">{{ $country }}</flux:pillbox.option>
                    @endforeach
                </flux:pillbox>

                <div class="space-y-3">
                    <flux:label>{{ __('Shipping rates') }}</flux:label>

                    @foreach ($rates as $index => $rate)
                        <div wire:key="rate-{{ $index }}" class="flex items-start gap-3">
                            <flux:input
                                wire:model="rates.{{ $index }}.name"
                                :placeholder="__('e.g. Standard delivery')"
                                :aria-label="__('Rate name')"
                                class="flex-1"
                            />
                            <flux:input
                                wire:model="rates.{{ $index }}.amount"
                                type="number"
                                min="0"
                                step="0.01"
                                :placeholder="__('Price')"
                                :aria-label="__('Rate price')"
                                class="w-32"
                            />
                            <flux:button
                                variant="subtle"
                                icon="x-mark"
                                wire:click="removeRate({{ $index }})"
                                :aria-label="__('Remove rate')"
                            />
                        </div>
                        <flux:error name="rates.{{ $index }}.name" />
                        <flux:error name="rates.{{ $index }}.amount" />
                    @endforeach

                    @if (count($rates) < \App\Services\ShopService::MAX_SHIPPING_RATES)
                        <flux:button size="sm" icon="plus" wire:click="addRate">{{ __('Add rate') }}</flux:button>
                    @endif
                </div>
            </div>

            <flux:separator variant="subtle" />

            <div class="space-y-6">
                <flux:heading size="lg">{{ __('Tax') }}</flux:heading>

                <flux:switch
                    wire:model.live="automaticTax"
                    :label="__('Calculate tax with Stripe Tax')"
                    :description="__('Turn Stripe Tax on in your Stripe dashboard first, or checkout will fail.')"
                    align="left"
                />

                <flux:switch
                    wire:model="pricesIncludeTax"
                    :label="__('Prices include tax')"
                    :disabled="! $automaticTax"
                    align="left"
                />
            </div>

            <div>
                <flux:button type="submit" variant="primary" icon="check"> {{ __('Update') }} </flux:button>
            </div>
        </div>
    </form>
</x-admin.settings-layout>

@section('header-content')
    <flux:breadcrumbs class="hidden md:flex">
        <flux:breadcrumbs.item href="{{ route('admin.settings-general') }}" wire:navigate>
            {{ __('Settings') }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item> {{ __('Shop') }} </flux:breadcrumbs.item>
    </flux:breadcrumbs>
    <flux:dropdown class="md:hidden">
        <flux:navbar.item icon-trailing="chevron-down">{{ __('Shop') }}</flux:navbar.item>

        <flux:navmenu>
            <flux:navmenu.item href="{{ route('admin.settings-general') }}" wire:navigate>
                {{ __('Settings') }}</flux:navmenu.item>
        </flux:navmenu>
    </flux:dropdown>
@endsection
