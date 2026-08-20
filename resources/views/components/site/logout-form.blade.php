@props(['url', 'label', 'buttonClass' => ''])

<form method="POST" action="{{ $url }}" class="contents">
    @csrf
    <button type="submit" class="{{ $buttonClass }}">{{ $label }}</button>
</form>
