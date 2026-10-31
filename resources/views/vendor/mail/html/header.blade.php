@props(['url', 'kicker' => null])
{{-- Feed-post author row: app avatar · wordmark · meta line. --}}
<tr>
<td class="post-head" style="padding: 22px 26px 0;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td width="40" valign="top" style="width: 40px;">
<a href="{{ $url }}" style="text-decoration: none; display: block;">
<table cellpadding="0" cellspacing="0" role="presentation"><tr>
<td class="post-avatar" width="40" height="40" align="center" valign="middle" style="width: 40px; height: 40px; background-color: #16b26a; border-radius: 13px; text-align: center;">
<span class="neaby-wordmark" style="font-family: 'Comfortaa', 'Trebuchet MS', 'Segoe UI', Arial, sans-serif; font-weight: 700; font-size: 20px; line-height: 40px; color: #ffffff; text-transform: lowercase;">n</span>
</td>
</tr></table>
</a>
</td>
<td valign="middle" style="padding-left: 14px;">
<a href="{{ $url }}" style="text-decoration: none; color: #141a17;"><span class="neaby-wordmark" style="font-family: 'Comfortaa', 'Trebuchet MS', 'Segoe UI', Arial, sans-serif; font-weight: 700; font-size: 16px; letter-spacing: -0.4px; color: #141a17; text-transform: lowercase; line-height: 1.3;">neaby</span></a><span style="display: inline-block; width: 6px; height: 6px; margin-left: 3px; border-radius: 999px; background-color: #16b26a; vertical-align: 1px;"></span>
<div class="post-meta" style="font-size: 14px; color: #67766e; line-height: 1.35; margin-top: 1px;">@neaby<span style="color: #c9d3ce;"> &middot; </span>{{ $kicker ?: 'just now' }}</div>
</td>
</tr>
</table>
</td>
</tr>
