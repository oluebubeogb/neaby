<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Remembers where a visitor was before they hit sign-in / sign-up so we can
 * send them straight back (e.g. the product they tapped "Buy now" on).
 *
 * Sources, in order: an explicit ?redirect=/path, an intended URL already
 * stored by the auth middleware, then the same-site Referer header (Inertia
 * visits send it). Only same-host, non-auth paths are accepted.
 */
class ReturnTo
{
    private const AUTH_PATHS = ['login', 'register', 'logout', 'forgot-password', 'reset-password', 'verify-email', 'confirm-password', 'email'];

    public static function remember(Request $request): void
    {
        $explicit = self::safe($request, (string) $request->query('redirect', ''));
        if ($explicit) {
            $request->session()->put('url.intended', $explicit);

            return;
        }
        if ($request->session()->has('url.intended')) {
            return;
        }
        if ($ref = self::safe($request, (string) $request->headers->get('referer', ''))) {
            $request->session()->put('url.intended', $ref);
        }
    }

    public static function safe(Request $request, string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 2000) {
            return null;
        }
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            $url = $request->getSchemeAndHttpHost().$url;
        }
        $parts = parse_url($url);
        if (! $parts || empty($parts['host']) || strcasecmp($parts['host'], $request->getHost()) !== 0) {
            return null;
        }
        $path = trim($parts['path'] ?? '/', '/');
        $first = explode('/', $path)[0];
        if (in_array($first, self::AUTH_PATHS, true) || str_starts_with($path, 'webhooks') || str_starts_with($path, 'share/') || str_starts_with($path, 'qr/')) {
            return null;
        }

        return $url;
    }
}
