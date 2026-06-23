<x-mail::message>
# {{ $formName !== '' ? __('New submission from :form', ['form' => $formName]) : __('New contact form submission') }}

{{ __('You received a new submission via your website.') }}

<x-mail::table>
| {{ __('Field') }} | {{ __('Details') }} |
| :--- | :--- |
@foreach ($rows as $row)
| **{{ $row['label'] }}** | {{ $row['value'] }} |
@endforeach
</x-mail::table>

@if ($message !== '')
**{{ __('Message') }}**

<x-mail::panel>
{{ $message }}
</x-mail::panel>
@endif

@if ($replyTo)
<x-mail::button :url="'mailto:'.$replyTo">
{{ __('Reply') }}
</x-mail::button>
@endif

@if ($submittedAt)
<small>{{ __('Received :date', ['date' => $submittedAt->format('j M Y, H:i')]) }}</small>
@endif

{{ __('Regards,') }}<br>
{{ config('app.name') }}
</x-mail::message>
