@props(['kicker' => null])
<x-mail::layout>
{{-- Header (post author row inside the card) --}}
<x-slot:header>
<x-mail::header :url="config('app.url')" :kicker="$kicker">
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
<a href="{{ config('app.url') }}" style="text-decoration: none;"><span class="neaby-wordmark" style="font-family: 'Comfortaa', 'Trebuchet MS', Arial, sans-serif; font-weight: 700; font-size: 15px; letter-spacing: -0.3px; color: #141a17; text-transform: lowercase;">neaby</span></a><br>
<span style="font-size: 13px; color: #67766e;">your store, your people, nearby.</span><br>
<span style="font-size: 12px; color: #94a39b;">© {{ date('Y') }} · you're getting this because you have a neaby account.</span>
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
