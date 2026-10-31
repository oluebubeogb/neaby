<?php

namespace App\Services\Sharing;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * "Sleek" neaby QR codes: rounded modules, brand-green diagonal gradient,
 * rounded finder eyes and a cleared centre with the neaby "n" mark.
 *
 * Scannability: ECC level H (30% recovery) with a centre cut-out of ~5% of
 * the symbol, ≥ 3:1 module contrast on white and a 3-module quiet zone.
 * Rendered 4× supersampled with GD, then downscaled for smooth edges.
 */
class QrCodes
{
    private const FROM = [16, 168, 104];   // #10a868 brand green
    private const TO = [8, 82, 74];        // #08524a deep teal
    private const EYE = [6, 64, 46];       // #06402e

    /** Raw PNG bytes for $url; $scale ≈ pixels per module (min output 360px). */
    public function png(string $url, int $scale = 10, bool $logo = true): string
    {
        $matrix = $this->matrix($url);
        $dim = $matrix->getSize();
        $target = max(360, min(1600, ($dim + 6) * $scale));

        return $this->render($matrix, $target, $logo);
    }

    /** GD image of the QR at exactly $px square (used inside share cards). */
    public function image(string $url, int $px, bool $logo = true): \GdImage
    {
        $bytes = $this->render($this->matrix($url), $px, $logo);

        return imagecreatefromstring($bytes);
    }

    private function matrix(string $url): QRMatrix
    {
        $options = new QROptions([
            'eccLevel' => EccLevel::H,
            'addQuietzone' => false,
        ]);
        $qr = new QRCode($options);
        $qr->addByteSegment($url);
        $matrix = $qr->getQRMatrix();
        $dim = $matrix->getSize();
        $logo = (int) round($dim * 0.22);
        $matrix->setLogoSpace($logo, $logo);

        return $matrix;
    }

    private function render(QRMatrix $matrix, int $target, bool $logo): string
    {
        $dim = $matrix->getSize();
        $quiet = 3;
        $ss = 4;
        // Integral pixels per module in the final image keeps module edges crisp.
        $mf = (int) max(2, floor($target / ($dim + 2 * $quiet)));
        $core = $mf * ($dim + 2 * $quiet);
        $m = $mf * $ss;
        $S = $m * ($dim + 2 * $quiet);
        $off = $quiet * $m;

        $im = imagecreatetruecolor($S, $S);
        imagefilledrectangle($im, 0, 0, $S, $S, imagecolorallocate($im, 255, 255, 255));

        $finder = [QRMatrix::M_FINDER_DARK, QRMatrix::M_FINDER_DOT, QRMatrix::M_FINDER, QRMatrix::M_SEPARATOR, QRMatrix::M_FINDER_DOT_LIGHT];
        $isEye = function (int $x, int $y) use ($dim): bool {
            return ($x < 7 && $y < 7) || ($x >= $dim - 7 && $y < 7) || ($x < 7 && $y >= $dim - 7);
        };

        // Data modules: rounded squares.
        $inset = (int) round($m * 0.03);
        $radius = (int) round($m * 0.3);
        for ($y = 0; $y < $dim; $y++) {
            for ($x = 0; $x < $dim; $x++) {
                if ($isEye($x, $y) || ! $matrix->check($x, $y)) {
                    continue;
                }
                $c = $this->grad($im, ($x + $y) / (2 * max(1, $dim - 1)));
                $px = $off + $x * $m;
                $py = $off + $y * $m;
                $this->roundRect($im, $px + $inset, $py + $inset, $px + $m - $inset, $py + $m - $inset, $radius, $c);
            }
        }

        // Finder eyes: thick rounded ring + rounded inner block.
        foreach ([[0, 0], [$dim - 7, 0], [0, $dim - 7]] as [$ex, $ey]) {
            $px = $off + $ex * $m;
            $py = $off + $ey * $m;
            $ring = $this->grad($im, ($ex + $ey + 3) / (2 * max(1, $dim - 1)));
            $this->roundRect($im, $px, $py, $px + 7 * $m, $py + 7 * $m, (int) round($m * 2.2), $ring);
            $white = imagecolorallocate($im, 255, 255, 255);
            $this->roundRect($im, $px + $m, $py + $m, $px + 6 * $m, $py + 6 * $m, (int) round($m * 1.4), $white);
            $this->roundRect($im, $px + 2 * $m, $py + 2 * $m, $px + 5 * $m, $py + 5 * $m, (int) round($m * 0.9), imagecolorallocate($im, ...self::EYE));
        }

        if ($logo) {
            $this->mark($im, $S / 2, $S / 2, (int) round($dim * 0.22 * $m * 0.5) - (int) ($m * 0.35), $m);
        }

        // Downscale for anti-aliasing.
        $out = imagecreatetruecolor($target, $target);
        imagefilledrectangle($out, 0, 0, $target, $target, imagecolorallocate($out, 255, 255, 255));
        $pad = (int) floor(($target - $core) / 2);
        imagecopyresampled($out, $im, $pad, $pad, 0, 0, $core, $core, $S, $S);
        imagedestroy($im);

        ob_start();
        imagepng($out, null, 6);
        imagedestroy($out);

        return (string) ob_get_clean();
    }

    /** neaby centre mark: white disc, gradient ring, Comfortaa "n" + dot. */
    private function mark(\GdImage $im, float $cx, float $cy, int $r, int $m): void
    {
        $cx = (int) $cx;
        $cy = (int) $cy;
        imagefilledellipse($im, $cx, $cy, 2 * $r, 2 * $r, $this->grad($im, 0.55));
        $ringW = max(2, (int) round($m * 0.32));
        imagefilledellipse($im, $cx, $cy, 2 * ($r - $ringW), 2 * ($r - $ringW), imagecolorallocate($im, 255, 255, 255));

        $font = resource_path('fonts/Comfortaa-Bold.ttf');
        $size = (int) round($r * 0.95);
        $b = imagettfbbox($size, 0, $font, 'n');
        $w = $b[2] - $b[0];
        $h = $b[1] - $b[7];
        $tx = (int) ($cx - $w / 2 - $b[0] - $r * 0.06);
        $ty = (int) ($cy + $h / 2 - $b[1]);
        imagettftext($im, $size, 0, $tx, $ty, imagecolorallocate($im, ...self::TO), $font, 'n');
        $dot = max(3, (int) round($r * 0.26));
        imagefilledellipse($im, $tx + $w + (int) ($dot * 0.9), $ty - (int) ($dot * 0.55), $dot, $dot, imagecolorallocate($im, ...self::FROM));
    }

    private function grad(\GdImage $im, float $t): int
    {
        $t = max(0.0, min(1.0, $t));
        [$r1, $g1, $b1] = self::FROM;
        [$r2, $g2, $b2] = self::TO;

        return imagecolorallocate($im, (int) ($r1 + ($r2 - $r1) * $t), (int) ($g1 + ($g2 - $g1) * $t), (int) ($b1 + ($b2 - $b1) * $t));
    }

    private function roundRect(\GdImage $im, int $x1, int $y1, int $x2, int $y2, int $r, int $c): void
    {
        $r = max(0, min($r, (int) (($x2 - $x1) / 2), (int) (($y2 - $y1) / 2)));
        if ($r === 0) {
            imagefilledrectangle($im, $x1, $y1, $x2 - 1, $y2 - 1, $c);

            return;
        }
        imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r - 1, $y2 - 1, $c);
        imagefilledrectangle($im, $x1, $y1 + $r, $x2 - 1, $y2 - $r - 1, $c);
        $d = 2 * $r;
        imagefilledellipse($im, $x1 + $r, $y1 + $r, $d, $d, $c);
        imagefilledellipse($im, $x2 - $r - 1, $y1 + $r, $d, $d, $c);
        imagefilledellipse($im, $x1 + $r, $y2 - $r - 1, $d, $d, $c);
        imagefilledellipse($im, $x2 - $r - 1, $y2 - $r - 1, $d, $d, $c);
    }
}
