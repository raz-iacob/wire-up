@php
    $settings = \App\Services\SettingsService::current();
    $brand = $settings->brandName();
    $mailLogo = $settings->mailLogo();
@endphp
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
@if ($mailLogo)
<img src="{{ $mailLogo['url'] }}" alt="{{ $brand }}" height="{{ $mailLogo['height'] }}" style="height:{{ $mailLogo['height'] }}px;max-width:100%;">
@else
{{ $brand }}
@endif
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $brand }}. {{ __('All rights reserved.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
