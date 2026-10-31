<?php

namespace App\Support;

/**
 * Server-rendered meta for crawlers & link previews (rendered in app.blade.php
 * on first load; Vue <Head> keeps titles in sync on client navigation).
 */
class Seo
{
    public static function make(
        string $title,
        ?string $description = null,
        ?string $image = null,
        ?string $canonical = null,
        string $type = 'website',
        ?array $jsonLd = null,
        bool $index = true,
    ): array {
        $brand = config('neaby.brand', 'neaby');
        $abs = fn (?string $u) => $u ? (str_starts_with($u, 'http') ? $u : url($u)) : null;

        return [
            'name' => $title,
            'title' => $title === $brand ? $brand : "{$title} · {$brand}",
            'description' => $description ?: 'neaby — discover products from stores near you, open your own storefront and sell in minutes.',
            'image' => $abs($image) ?: url('/og-default.png'),
            'canonical' => $canonical ?: url()->current(),
            'type' => $type,
            'json_ld' => $jsonLd,
            'robots' => $index ? 'index,follow' : 'noindex,nofollow',
        ];
    }
}
