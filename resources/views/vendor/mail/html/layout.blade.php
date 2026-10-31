<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<title>{{ config('app.name') }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="color-scheme" content="light">
<meta name="x-apple-disable-message-reformatting">
<meta name="supported-color-schemes" content="light">
{{-- Same families as the site: Comfortaa (wordmark) + Inter (copy). Clients that strip
     <link>/@font-face (e.g. Gmail) fall back to the system sans stack. --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Comfortaa:wght@700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
@import url('https://fonts.googleapis.com/css2?family=Comfortaa:wght@700&family=Inter:wght@400;500;600;700&display=swap');
@font-face {
font-family: 'Comfortaa';
font-style: normal;
font-weight: 700;
font-display: swap;
src: url('{{ rtrim(config('app.url'), '/') }}/fonts/comfortaa-700.woff2') format('woff2');
}
@font-face {
font-family: 'Neaby Naira';
font-style: normal;
font-weight: 100 900;
src: url('{{ rtrim(config('app.url'), '/') }}/fonts/naira-var.woff2') format('woff2');
unicode-range: U+20A6;
}
.neaby-wordmark {
font-family: 'Comfortaa', 'Trebuchet MS', 'Segoe UI', Arial, sans-serif !important;
font-weight: 700 !important;
text-transform: lowercase;
}
@media only screen and (max-width: 600px) {
.content { padding-top: 0 !important; }
.inner-body {
width: 100% !important;
border-radius: 0 !important;
border-left: 0 !important;
border-right: 0 !important;
box-shadow: none !important;
}
.footer { width: 100% !important; }
.post-head { padding: 20px 18px 0 !important; }
.content-cell { padding: 14px 18px 22px 18px !important; }
}
@media only screen and (max-width: 420px) {
.button { display: block !important; text-align: center !important; }
}
</style>
{!! $head ?? '' !!}
</head>
<body>

<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<table class="content" width="100%" cellpadding="0" cellspacing="0" role="presentation">

<!-- Email Body -->
<tr>
<td class="body" width="100%" cellpadding="0" cellspacing="0" style="border: hidden !important;">
<table class="inner-body" align="center" width="540" cellpadding="0" cellspacing="0" role="presentation">
{!! $header ?? '' !!}
<!-- Body content -->
<tr>
<td class="content-cell">
{!! Illuminate\Mail\Markdown::parse($slot) !!}

{!! $subcopy ?? '' !!}
</td>
</tr>
</table>
</td>
</tr>

{!! $footer ?? '' !!}
</table>
</td>
</tr>
</table>
</body>
</html>
