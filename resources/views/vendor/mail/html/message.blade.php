<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name') }}
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
© {{ date('Y') }} <span class="neaby-wordmark" style="font-family: 'Comfortaa', 'Trebuchet MS', Arial, sans-serif; font-weight: 700;">neaby</span> · Shop what's near you.
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
