<?php

namespace App\Services\Sharing;

use App\Models\Product;
use App\Models\ResellerListing;
use App\Models\Store;
use App\Support\Present;
use Illuminate\Support\Facades\Storage;

/**
 * Server-rendered PNG share cards (blueprint 8): product, store and reseller
 * offer cards in square / portrait / landscape, each with a QR back to neaby.
 * Rendered with GD + bundled fonts (Comfortaa for the wordmark), cached on
 * the private local disk keyed by content, so edits invalidate automatically.
 */
class ShareCards
{
    public const FORMATS = [
        'square' => [1080, 1080],
        'portrait' => [1080, 1350],
        'landscape' => [1200, 630],
    ];

    private const VERSION = 'v4';

    public function __construct(private readonly QrCodes $qr) {}

    public function product(Product $p, string $format): string
    {
        $p->loadMissing(['cover', 'store']);

        return $this->cached('p', $p->id, $format, [$p->updated_at?->timestamp, $p->price_minor, $p->cover?->path, $p->store?->name], fn () => $this->render($format, [
            'image' => $this->diskPath($p->cover?->path),
            'title' => $p->title,
            'price' => Present::money($p->price_minor, $p->currency),
            'compare' => $p->compare_at_minor && $p->compare_at_minor > $p->price_minor ? Present::money($p->compare_at_minor) : null,
            'subtitle' => trim(($p->store?->name ?? '').($p->city ? ' · '.$p->city : '')),
            'accent' => $p->store?->accent ?: '#16a34a',
            'url' => route('products.show', $p).'?ch=qr',
            'badge' => $p->condition === 'new' ? 'New' : ($p->condition === 'used' ? 'Fairly used' : 'Refurbished'),
        ]));
    }

    public function store(Store $s, string $format): string
    {
        return $this->cached('s', $s->id, $format, [$s->updated_at?->timestamp, $s->logo_path, $s->cover_path], fn () => $this->render($format, [
            'image' => $this->diskPath($s->cover_path ?: $s->logo_path),
            'title' => $s->name,
            'price' => null,
            'compare' => null,
            'subtitle' => $s->tagline ?: trim('Shop on neaby'.($s->city ? ' · '.$s->city : '')),
            'accent' => $s->accent ?: '#16a34a',
            'url' => route('stores.show', $s).'?ch=qr',
            'badge' => 'Store',
        ]));
    }

    public function offer(ResellerListing $l, string $format): string
    {
        $l->loadMissing(['product.cover', 'reseller']);

        // Shows the reseller's own selling price only — never the supplier's protected price.
        return $this->cached('r', $l->id, $format, [$l->updated_at?->timestamp, $l->selling_price_minor, $l->product?->cover?->path], fn () => $this->render($format, [
            'image' => $this->diskPath($l->product?->cover?->path),
            'title' => $l->title(),
            'price' => Present::money($l->selling_price_minor, $l->currency),
            'compare' => null,
            'subtitle' => 'Offered by @'.$l->reseller?->username,
            'accent' => '#16a34a',
            'url' => route('offers.show', $l).'?ch=qr',
            'badge' => 'Reseller offer',
        ]));
    }

    private function cached(string $kind, int $id, string $format, array $parts, callable $make): string
    {
        $format = isset(self::FORMATS[$format]) ? $format : 'square';
        $hash = substr(sha1(json_encode([self::VERSION, $parts, config('app.url')])), 0, 16);
        $path = "share-cards/{$kind}{$id}-{$format}-{$hash}.png";
        $disk = Storage::disk('local');
        if ($disk->exists($path)) {
            return $disk->get($path);
        }
        // Drop stale variants for this target.
        foreach ($disk->files('share-cards') as $old) {
            if (str_starts_with(basename($old), "{$kind}{$id}-{$format}-")) {
                $disk->delete($old);
            }
        }
        $png = $make();
        $disk->put($path, $png);

        return $png;
    }

    private function diskPath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        $disk = Storage::disk(config('neaby.uploads.disk', 'public'));

        return $disk->exists($path) ? $disk->path($path) : null;
    }

    private function font(string $name): string
    {
        return resource_path("fonts/{$name}.ttf");
    }

    private function color(\GdImage $im, string $hex, int $alpha = 0): int
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = preg_replace('/(.)/', '$1$1', $hex);
        }
        [$r, $g, $b] = array_map('hexdec', str_split(str_pad(substr($hex, 0, 6), 6, '0'), 2));

        return imagecolorallocatealpha($im, $r, $g, $b, $alpha);
    }

    /**
     * Layout: the photo is the message (~80% of the card), with a slim white
     * band carrying price + title and a sleek QR tile that overlaps the photo.
     */
    private function render(string $format, array $d): string
    {
        [$W, $H] = self::FORMATS[$format] ?? self::FORMATS['square'];
        $im = imagecreatetruecolor($W, $H);
        imagealphablending($im, true);
        imagesavealpha($im, false);
        imagefilledrectangle($im, 0, 0, $W, $H, $this->color($im, '#ffffff'));
        $bold = $this->font('PlusJakartaSans-ExtraBold');
        $med = $this->font('PlusJakartaSans-Medium');
        $ink = $this->color($im, '#0f172a');
        $muted = $this->color($im, '#64748b');
        $green = $this->color($im, '#059669');

        if ($format === 'landscape') {
            $iw = (int) round($W * 0.8);
            $this->drawCover($im, $d['image'], 0, 0, $iw, $H, $d['accent'], $d['title']);
            $this->brandPill($im, 28, 28, 26);
            if (! empty($d['badge'])) {
                $this->pill($im, $iw - 28 - $this->pillWidth($d['badge'], 18), 28, $d['badge'], 18, '#ffffff', '#0f172a');
            }
            // Right rail: QR tile, price, title.
            $px = $iw + 20;
            $pw = $W - $iw - 40;
            $tile = $pw;
            $this->qrTile($im, $d['url'], $px, 28, $tile);
            $y = 28 + $tile + 40;
            if (! empty($d['price'])) {
                $size = $this->fit($d['price'], $bold, 30, $pw);
                imagettftext($im, $size, 0, $px, $y, $green, $bold, $d['price']);
                $y += 34;
            }
            foreach ($this->wrap($d['title'], $med, 17, $pw, 3) as $line) {
                imagettftext($im, 17, 0, $px, $y, $ink, $med, $line);
                $y += 25;
            }
            if (! empty($d['subtitle'])) {
                $sub = $this->wrap($d['subtitle'], $med, 14, $pw, 1)[0] ?? '';
                imagettftext($im, 14, 0, $px, min($H - 24, $y + 8), $muted, $med, $sub);
            }
        } else {
            $ih = (int) round($H * 0.8);
            $this->drawCover($im, $d['image'], 0, 0, $W, $ih, $d['accent'], $d['title']);
            $this->brandPill($im, 40, 40, 34);
            if (! empty($d['badge'])) {
                $this->pill($im, $W - 40 - $this->pillWidth($d['badge'], 24), 40, $d['badge'], 24, '#ffffff', '#0f172a');
            }
            $band = $H - $ih;
            $tile = (int) round($band * 1.12);
            $pad = 44;
            $qx = $W - $pad - $tile;
            $qy = $H - $pad + 8 - $tile;
            $this->qrTile($im, $d['url'], $qx, $qy, $tile);

            $tw = $qx - $pad - 32;
            $priceSize = $format === 'portrait' ? 54 : 46;
            $titleSize = $format === 'portrait' ? 28 : 25;
            $subSize = $format === 'portrait' ? 21 : 19;
            $hasPrice = ! empty($d['price']);
            $titleLines = $this->wrap($d['title'], $hasPrice ? $med : $bold, $hasPrice ? $titleSize : $priceSize - 10, $tw, 1);
            $blockH = ($hasPrice ? $priceSize + 18 : 0) + ($hasPrice ? $titleSize : $priceSize - 10) + 14 + $subSize;
            $y = $ih + (int) (($band - $blockH) / 2);
            if ($hasPrice) {
                $y += $priceSize;
                imagettftext($im, $priceSize, 0, $pad, $y, $green, $bold, $d['price']);
                if (! empty($d['compare'])) {
                    $box = imagettfbbox($priceSize, 0, $bold, $d['price']);
                    $cx = $pad + ($box[2] - $box[0]) + 20;
                    imagettftext($im, $subSize + 2, 0, $cx, $y, $this->color($im, '#94a3b8'), $med, $d['compare']);
                    $cb = imagettfbbox($subSize + 2, 0, $med, $d['compare']);
                    imagefilledrectangle($im, $cx, $y - (int) ($subSize * 0.38), $cx + ($cb[2] - $cb[0]), $y - (int) ($subSize * 0.38) + 2, $this->color($im, '#94a3b8'));
                }
                $y += 18;
            }
            $ts = $hasPrice ? $titleSize : $priceSize - 10;
            $y += $ts;
            imagettftext($im, $ts, 0, $pad, $y, $ink, $hasPrice ? $med : $bold, $titleLines[0] ?? '');
            if (! empty($d['subtitle'])) {
                $y += 14 + $subSize;
                $sub = $this->wrap($d['subtitle'], $med, $subSize, $tw, 1)[0] ?? '';
                imagettftext($im, $subSize, 0, $pad, $y, $muted, $med, $sub);
            }
        }

        ob_start();
        imagepng($im, null, 6);

        return (string) ob_get_clean();
    }

    /** White rounded tile with a soft edge + sleek QR inside. */
    private function qrTile(\GdImage $im, string $url, int $x, int $y, int $size): void
    {
        $r = (int) round($size * 0.14);
        $this->roundRect($im, $x - 2, $y + 4, $x + $size + 2, $y + $size + 8, $r, $this->color($im, '#0f172a', 112)); // shadow
        $this->roundRect($im, $x, $y, $x + $size, $y + $size, $r, $this->color($im, '#ffffff'));
        $inner = $size - (int) round($size * 0.06) * 2;
        $qr = $this->qr->image($url, $inner);
        $o = (int) (($size - $inner) / 2);
        imagecopy($im, $qr, $x + $o, $y + $o, 0, 0, $inner, $inner);
        imagedestroy($qr);
    }

    /** Small white "neaby." pill (Comfortaa wordmark) on the photo. */
    private function brandPill(\GdImage $im, int $x, int $y, int $size): void
    {
        $font = $this->font('Comfortaa-Bold');
        $b = imagettfbbox($size, 0, $font, 'neaby');
        $tw = $b[2] - $b[0];
        $dot = (int) round($size * 0.3);
        $h = (int) round($size * 1.9);
        $w = $tw + $dot + (int) round($size * 1.5);
        $this->roundRect($im, $x, $y, $x + $w, $y + $h, (int) ($h / 2), $this->color($im, '#ffffff', 12));
        $tx = $x + (int) round($size * 0.62);
        $ty = $y + (int) (($h + $size) / 2) - 2;
        imagettftext($im, $size, 0, $tx, $ty, $this->color($im, '#0f172a'), $font, 'neaby');
        imagefilledellipse($im, $tx + $tw + (int) ($dot * 0.9), $ty - (int) ($dot / 2), $dot, $dot, $this->color($im, '#16b26a'));
    }

    private function pillWidth(string $text, int $size): int
    {
        $b = imagettfbbox($size, 0, $this->font('PlusJakartaSans-ExtraBold'), $text);

        return ($b[2] - $b[0]) + 44;
    }

    private function fit(string $text, string $font, int $size, int $max): int
    {
        while ($size > 12) {
            $b = imagettfbbox($size, 0, $font, $text);
            if (($b[2] - $b[0]) <= $max) {
                break;
            }
            $size--;
        }

        return $size;
    }

    private function roundRect(\GdImage $im, int $x1, int $y1, int $x2, int $y2, int $r, int $c): void
    {
        $r = max(0, min($r, (int) (($x2 - $x1) / 2), (int) (($y2 - $y1) / 2)));
        imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $c);
        imagefilledrectangle($im, $x1, $y1 + $r, $x1 + $r - 1, $y2 - $r, $c);
        imagefilledrectangle($im, $x2 - $r + 1, $y1 + $r, $x2, $y2 - $r, $c);
        $d = 2 * $r;
        imagefilledarc($im, $x1 + $r, $y1 + $r, $d, $d, 180, 270, $c, IMG_ARC_PIE);
        imagefilledarc($im, $x2 - $r, $y1 + $r, $d, $d, 270, 360, $c, IMG_ARC_PIE);
        imagefilledarc($im, $x1 + $r, $y2 - $r, $d, $d, 90, 180, $c, IMG_ARC_PIE);
        imagefilledarc($im, $x2 - $r, $y2 - $r, $d, $d, 0, 90, $c, IMG_ARC_PIE);
    }

    private function drawCover(\GdImage $im, ?string $path, int $x, int $y, int $w, int $h, string $accent, string $title): void
    {
        $src = null;
        if ($path && is_readable($path)) {
            $bytes = @file_get_contents($path);
            $src = $bytes ? @imagecreatefromstring($bytes) : null;
        }
        if (! $src) {
            // Branded placeholder: accent gradient + initial.
            for ($i = 0; $i < $h; $i++) {
                $t = $i / max(1, $h);
                $c = $this->mix($accent, '#0f172a', 0.15 + 0.35 * $t);
                imageline($im, $x, $y + $i, $x + $w, $y + $i, $this->color($im, $c));
            }
            $letter = mb_strtoupper(mb_substr($title, 0, 1)) ?: 'n';
            $size = (int) min($w, $h) * 0.38;
            $b = imagettfbbox($size, 0, $this->font('PlusJakartaSans-ExtraBold'), $letter);
            imagettftext($im, $size, 0, $x + (int) (($w - ($b[2] - $b[0])) / 2), $y + (int) (($h + ($b[1] - $b[7])) / 2), $this->color($im, '#ffffff', 40), $this->font('PlusJakartaSans-ExtraBold'), $letter);

            return;
        }
        $sw = imagesx($src);
        $sh = imagesy($src);
        $scale = max($w / $sw, $h / $sh); // cover
        $cw = (int) ($w / $scale);
        $ch = (int) ($h / $scale);
        imagecopyresampled($im, $src, $x, $y, (int) (($sw - $cw) / 2), (int) (($sh - $ch) / 2), $w, $h, $cw, $ch);
    }

    private function pill(\GdImage $im, int $x, int $y, string $text, int $size, string $bg, string $fg): void
    {
        $font = $this->font('PlusJakartaSans-ExtraBold');
        $b = imagettfbbox($size, 0, $font, $text);
        $w = ($b[2] - $b[0]) + 44;
        $h = $size + 30;
        $r = (int) ($h / 2);
        $c = $this->color($im, $bg, 10);
        imagefilledrectangle($im, $x + $r, $y, $x + $w - $r, $y + $h, $c);
        imagefilledellipse($im, $x + $r, $y + $r, $h, $h, $c);
        imagefilledellipse($im, $x + $w - $r, $y + $r, $h, $h, $c);
        imagettftext($im, $size, 0, $x + 22, $y + $size + 14, $this->color($im, $fg), $font, $text);
    }

    /** @return list<string> */
    private function wrap(string $text, string $font, int $size, int $max, int $maxLines): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $lines = [];
        $cur = '';
        foreach ($words as $word) {
            $try = $cur === '' ? $word : "{$cur} {$word}";
            $b = imagettfbbox($size, 0, $font, $try);
            if (($b[2] - $b[0]) <= $max || $cur === '') {
                $cur = $try;
            } else {
                $lines[] = $cur;
                $cur = $word;
            }
        }
        if ($cur !== '') {
            $lines[] = $cur;
        }
        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $last = $lines[$maxLines - 1];
            while (mb_strlen($last) > 1) {
                $b = imagettfbbox($size, 0, $font, $last.'…');
                if (($b[2] - $b[0]) <= $max) {
                    break;
                }
                $last = mb_substr($last, 0, -1);
            }
            $lines[$maxLines - 1] = rtrim($last).'…';
        }

        return $lines;
    }

    private function mix(string $a, string $b, float $t): string
    {
        $pa = array_map('hexdec', str_split(ltrim(strlen(ltrim($a, '#')) === 3 ? preg_replace('/(.)/', '$1$1', ltrim($a, '#')) : $a, '#'), 2));
        $pb = array_map('hexdec', str_split(ltrim($b, '#'), 2));
        $c = [];
        for ($i = 0; $i < 3; $i++) {
            $c[] = (int) round(($pa[$i] ?? 0) * (1 - $t) + ($pb[$i] ?? 0) * $t);
        }

        return sprintf('#%02x%02x%02x', ...$c);
    }
}
