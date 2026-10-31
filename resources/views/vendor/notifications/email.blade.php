<x-mail::message>
{{-- Greeting --}}
@if (! empty($greeting))
# {{ $greeting }}
@elseif ($level === 'error')
# Something needs your attention
@else
# Hello from neaby
@endif

{{-- Intro Lines --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Action Button --}}
@isset($actionText)
<x-mail::button :url="$actionUrl" :color="$level === 'error' ? 'error' : 'primary'">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Outro Lines --}}
@foreach ($outroLines as $line)
<p style="font-size: 13px; color: #64748b;">{{ $line }}</p>

@endforeach

{{-- Salutation --}}
@if (! empty($salutation))
<p style="margin-top: 22px; color: #0f172a; font-weight: 600;">{{ $salutation }}</p>
@else
<p style="margin-top: 22px; color: #0f172a; font-weight: 600;">— The <span class="neaby-wordmark" style="font-family: 'Comfortaa', 'Trebuchet MS', Arial, sans-serif; font-weight: 700;">neaby</span> team</p>
@endif

{{-- Subcopy --}}
@isset($actionText)
<x-slot:subcopy>
Button not working? Paste this link into your browser:
<span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
