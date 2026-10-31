<?php

namespace App\Services\Sharing;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class QrCodes
{
    /** Raw PNG bytes for $url. */
    public function png(string $url, int $scale = 10): string
    {
        $options = new QROptions([
            'outputType' => \chillerlan\QRCode\Output\QROutputInterface::GDIMAGE_PNG,
            'outputBase64' => false,
            'scale' => $scale,
            'quietzoneSize' => 2,
            'eccLevel' => \chillerlan\QRCode\Common\EccLevel::M,
            'drawLightModules' => true,
            'bgColor' => [255, 255, 255],
            'moduleValues' => [
                // dark modules in neaby ink
                \chillerlan\QRCode\Data\QRMatrix::M_DATA_DARK => [15, 23, 42],
                \chillerlan\QRCode\Data\QRMatrix::M_FINDER_DARK => [5, 150, 105],
                \chillerlan\QRCode\Data\QRMatrix::M_FINDER_DOT => [5, 150, 105],
                \chillerlan\QRCode\Data\QRMatrix::M_ALIGNMENT_DARK => [15, 23, 42],
                \chillerlan\QRCode\Data\QRMatrix::M_TIMING_DARK => [15, 23, 42],
                \chillerlan\QRCode\Data\QRMatrix::M_FORMAT_DARK => [15, 23, 42],
                \chillerlan\QRCode\Data\QRMatrix::M_VERSION_DARK => [15, 23, 42],
                \chillerlan\QRCode\Data\QRMatrix::M_DARKMODULE => [15, 23, 42],
                \chillerlan\QRCode\Data\QRMatrix::M_QUIETZONE => [255, 255, 255],
            ],
        ]);

        return (new QRCode($options))->render($url);
    }
}
