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
<span class="neaby-wordmark" style="font-family: 'Comfortaa', 'Trebuchet MS', Arial, sans-serif; font-weight: 700; color: #0f172a; font-size: 15px;">neaby</span><br>
Your store, your people, nearby.<br>
<span style="color: #cbd5e1;">© {{ date('Y') }} · You're receiving this because you have a neaby account.</span>
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
