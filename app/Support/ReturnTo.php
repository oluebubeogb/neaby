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

    /**
     * Hub pages: landing on these after sign-in isn't "going back" to anything,
     * so we open /home instead (the inbox used to win via the Referer / nav).
     */
    private const HUB_PATHS = ['', 'home', 'inbox', 'messages', 'notifications', 'dashboard', 'profile', 'settings', 'account'];

    public static function remember(Request $request): void
    {
        $explicit = self::safe($request, (string) $request->query('redirect', ''));
        if ($explicit) {
            $request->session()->put('url.intended', $explicit);
            $request->session()->put('url.intended_explicit', true);

            return;
        }
        if ($request->session()->has('url.intended')) {
            return;
        }
        if ($ref = self::safe($request, (string) $request->headers->get('referer', ''))) {
            $request->session()->put('url.intended', $ref);
            $request->session()->forget('url.intended_explicit');
        }
    }

    /**
     * Where to go right after sign-in / sign-up: an explicit ?redirect= always
     * wins (e.g. "Buy now" → back to the product); any other remembered page
     * is honoured only when it's real content; otherwise /home.
     */
    public static function afterAuth(Request $request): string
    {
        $home = route('home', absolute: false);
        $intended = (string) $request->session()->pull('url.intended', '');
        $explicit = (bool) $request->session()->pull('url.intended_explicit', false);
        $url = $intended !== '' ? self::safe($request, $intended) : null;
        if (! $url) {
            return $home;
        }
        if ($explicit) {
            return $url;
        }
        $path = trim((string) (parse_url($url, PHP_URL_PATH) ?? '/'), '/');
        $first = explode('/', $path)[0];
        if (in_array($first, self::HUB_PATHS, true)) {
            return $home;
        }

        return $url;
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
