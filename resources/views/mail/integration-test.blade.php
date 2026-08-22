<x-mail::message>
# {{ __('Your email settings work') }}

{{ __('This is a test message sent from :site. Now that it has reached you, form notifications and system email will too.', ['site' => $brand]) }}

{{ __('Regards,') }}<br>
{{ $brand }}
</x-mail::message>
