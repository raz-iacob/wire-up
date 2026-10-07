<x-mail::message>
# {{ __('Thank you for your order') }}

{{ __('Your order :reference is confirmed. Here is what you bought.', ['reference' => $order->reference]) }}

<x-mail::table>
| {{ __('Item') }} | {{ __('Quantity') }} | {{ __('Amount') }} |
| :--- | :---: | ---: |
@foreach ($order->items as $item)
| {{ $item->name }} | {{ $item->quantity }} | {{ $shop->formatMinor($item->line_amount, $order->currency) }} |
@endforeach
@if ($order->shipping_amount > 0)
| {{ __('Shipping') }} | | {{ $shop->formatMinor($order->shipping_amount, $order->currency) }} |
@endif
@if ($order->tax_amount > 0)
| {{ __('Tax') }} | | {{ $shop->formatMinor($order->tax_amount, $order->currency) }} |
@endif
| **{{ __('Total') }}** | | **{{ $shop->formatMinor($order->total_amount, $order->currency) }}** |
</x-mail::table>

@if (is_array($order->shipping_address))
**{{ __('Shipping to') }}**<br>
{{ data_get($order->shipping_address, 'name') }}<br>
{{ collect(data_get($order->shipping_address, 'address', []))->filter()->implode(', ') }}
@endif

{{ __('Regards,') }}<br>
{{ \App\Services\SettingsService::current()->brandName() }}
</x-mail::message>
