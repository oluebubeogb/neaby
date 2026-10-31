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

    private const VERSION = 'v3';

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

    private function render(string $format, array $d): string
    {
        [$W, $H] = self::FORMATS[$format] ?? self::FORMATS['square'];
        $im = imagecreatetruecolor($W, $H);
        imagealphablending($im, true);
        imagesavealpha($im, false);
        imagefilledrectangle($im, 0, 0, $W, $H, $this->color($im, '#ffffff'));

        $landscape = $format === 'landscape';
        $pad = $landscape ? 48 : 64;

        // Image region.
        [$ix, $iy, $iw, $ih] = $landscape ? [0, 0, (int) ($W * 0.48), $H] : [0, 0, $W, (int) ($H * ($format === 'portrait' ? 0.6 : 0.56))];
        $this->drawCover($im, $d['image'], $ix, $iy, $iw, $ih, $d['accent'], $d['title']);

        // Badge on the image.
        if (! empty($d['badge'])) {
            $this->pill($im, $ix + $pad - 16, $iy + $pad - 16, $d['badge'], 26, '#ffffff', '#0f172a');
        }

        // Text panel.
        $tx = $landscape ? $iw + $pad : $pad;
        $tw = ($landscape ? $W - $iw : $W) - 2 * $pad;
        $ty = $landscape ? $pad + 20 : $ih + $pad;
        $qrSize = $landscape ? 170 : ($format === 'portrait' ? 230 : 200);
        $textWidth = $landscape ? $tw : $tw - $qrSize - 32;

        $titleSize = $landscape ? 38 : 46;
        $lines = $this->wrap($d['title'], $this->font('PlusJakartaSans-ExtraBold'), $titleSize, $textWidth, 2);
        $y = $ty + $titleSize;
        foreach ($lines as $line) {
            imagettftext($im, $titleSize, 0, $tx, $y, $this->color($im, '#0f172a'), $this->font('PlusJakartaSans-ExtraBold'), $line);
            $y += (int) ($titleSize * 1.32);
        }

        if (! empty($d['price'])) {
            $y += 12;
            $ps = $landscape ? 50 : 60;
            imagettftext($im, $ps, 0, $tx, $y + $ps, $this->color($im, '#059669'), $this->font('PlusJakartaSans-ExtraBold'), $d['price']);
            if (! empty($d['compare'])) {
                $box = imagettfbbox($ps, 0, $this->font('PlusJakartaSans-ExtraBold'), $d['price']);
                $cx = $tx + ($box[2] - $box[0]) + 24;
                imagettftext($im, 30, 0, $cx, $y + $ps, $this->color($im, '#94a3b8'), $this->font('PlusJakartaSans-Medium'), $d['compare']);
                $cb = imagettfbbox(30, 0, $this->font('PlusJakartaSans-Medium'), $d['compare']);
                imagefilledrectangle($im, $cx, $y + $ps - 11, $cx + ($cb[2] - $cb[0]), $y + $ps - 9, $this->color($im, '#94a3b8'));
            }
            $y += $ps + 24;
        }

        if (! empty($d['subtitle'])) {
            $sub = $this->wrap($d['subtitle'], $this->font('PlusJakartaSans-Medium'), 28, $textWidth, 1)[0] ?? '';
            imagettftext($im, 28, 0, $tx, $y + 34, $this->color($im, '#475569'), $this->font('PlusJakartaSans-Medium'), $sub);
        }

        // Wordmark bottom-left (Comfortaa, lowercase) + green dot.
        $wm = 44;
        $wy = $H - $pad + ($landscape ? 4 : 0);
        imagettftext($im, $wm, 0, $tx, $wy, $this->color($im, '#0f172a'), $this->font('Comfortaa-Bold'), 'neaby');
        $wb = imagettfbbox($wm, 0, $this->font('Comfortaa-Bold'), 'neaby');
        $dot = (int) ($wm * 0.3);
        imagefilledellipse($im, $tx + ($wb[2] - $wb[0]) + $dot, $wy - (int) ($dot / 2), $dot, $dot, $this->color($im, '#16b26a'));

        // QR bottom-right.
        $qr = imagecreatefromstring($this->qr->png($d['url'], 8));
        $qx = $W - $pad - $qrSize;
        $qy = $H - $pad - $qrSize - ($landscape ? 0 : 10);
        imagefilledrectangle($im, $qx - 10, $qy - 10, $qx + $qrSize + 10, $qy + $qrSize + 10, $this->color($im, '#f1f5f9'));
        imagecopyresampled($im, $qr, $qx, $qy, 0, 0, $qrSize, $qrSize, imagesx($qr), imagesy($qr));
        imagettftext($im, 18, 0, $qx, $qy - 22, $this->color($im, '#64748b'), $this->font('PlusJakartaSans-Medium'), 'Scan to view');

        ob_start();
        imagepng($im, null, 6);

        return (string) ob_get_clean();
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
