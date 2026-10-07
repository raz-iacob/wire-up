<x-mail::message>
# {{ __('New order :reference', ['reference' => $order->reference]) }}

{{ __(':customer paid :total.', ['customer' => $order->name ?: ($order->email ?: __('A guest')), 'total' => $shop->formatMinor($order->total_amount, $order->currency)]) }}

@if ($order->oversold)
<x-mail::panel>
{{ __('This payment arrived after the checkout had expired and there was not enough stock left. Check your stock before you ship it.') }}
</x-mail::panel>
@endif

<x-mail::table>
| {{ __('Item') }} | {{ __('Quantity') }} | {{ __('Amount') }} |
| :--- | :---: | ---: |
@foreach ($order->items as $item)
| {{ $item->name }} | {{ $item->quantity }} | {{ $shop->formatMinor($item->line_amount, $order->currency) }} |
@endforeach
</x-mail::table>

@if (is_array($order->shipping_address))
**{{ __('Ship to') }}**<br>
{{ data_get($order->shipping_address, 'name') }}<br>
{{ collect(data_get($order->shipping_address, 'address', []))->filter()->implode(', ') }}
@endif

<x-mail::button :url="$viewUrl">
{{ __('View order') }}
</x-mail::button>

{{ __('Regards,') }}<br>
{{ \App\Services\SettingsService::current()->brandName() }}
</x-mail::message>
