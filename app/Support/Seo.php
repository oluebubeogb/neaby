<?php

namespace App\Support;

use App\Services\Sharing\ShareCards;

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
        ?array $imageSize = null,
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
            'image_size' => $imageSize,
        ];
    }

    /**
     * Share-card format requested by a shared link (?card=portrait|landscape|square).
     * The share sheet appends it so the link preview is the exact card the sharer saw.
     */
    public static function cardFormat(?string $requested): string
    {
        return array_key_exists((string) $requested, ShareCards::FORMATS) ? (string) $requested : 'square';
    }

    /** [url, [w, h]] for a share-card route in the requested format. */
    public static function card(string $routeName, mixed $model, ?string $requested): array
    {
        $format = self::cardFormat($requested);
        $url = route($routeName, $model).($format === 'square' ? '' : '?format='.$format);

        return [$url, ShareCards::FORMATS[$format]];
    }
}
