<x-mail::message :kicker="$kicker ?? null">
{{-- Greeting — bold, body-sized (the "post" opener) --}}
@if (! empty($greeting))
<p style="font-size: 15px; font-weight: 700; color: #141a17; margin: 0 0 4px;">{{ $greeting }}</p>
@elseif ($level === 'error')
<p style="font-size: 15px; font-weight: 700; color: #141a17; margin: 0 0 4px;">Something needs your attention</p>
@else
<p style="font-size: 15px; font-weight: 700; color: #141a17; margin: 0 0 4px;">Hey 👋</p>
@endif

{{-- Intro Lines --}}
@foreach ($introLines as $line)
<p style="font-size: 15px; color: #36423c; margin: 0 0 12px;">{{ $line }}</p>
@endforeach

{{-- Action Button --}}
@isset($actionText)
<x-mail::button :url="$actionUrl" :color="$level === 'error' ? 'error' : 'primary'">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Outro Lines — same size, quieter colour --}}
@foreach ($outroLines as $line)
<p style="font-size: 14px; color: #67766e; margin: 0 0 8px;">{{ $line }}</p>
@endforeach

{{-- Salutation --}}
@if (! empty($salutation))
<p style="font-size: 15px; margin: 16px 0 0; color: #141a17; font-weight: 600;">{{ $salutation }}</p>
@else
<p style="font-size: 15px; margin: 16px 0 0; color: #141a17; font-weight: 600;">— the <span class="neaby-wordmark" style="font-family: 'Comfortaa', 'Trebuchet MS', Arial, sans-serif; font-weight: 700; letter-spacing: -0.3px;">neaby</span> team</p>
@endif

{{-- Subcopy --}}
@isset($actionText)
<x-slot:subcopy>
Button not working? Paste this into your browser:<br>
<a href="{{ $actionUrl }}" class="link break-all" style="color: #4b5952; font-weight: 500; word-break: break-all;">{{ $displayableActionUrl }}</a>
</x-slot:subcopy>
@endisset
</x-mail::message>
