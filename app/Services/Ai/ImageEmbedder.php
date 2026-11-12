<?php

namespace App\Services\Ai;

use App\Models\ProductImage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Embeds listing photos with CLIP and stores the vectors. Used by the
 * EmbedProductImage job (on upload / photo change) and the backfill command.
 * Never throws: a failed image is marked and retried later by the scheduler.
 */
class ImageEmbedder
{
    public function __construct(private readonly ReplicateClip $clip, private readonly Embeddings $embeddings) {}

    public function ready(): bool
    {
        return $this->clip->enabled() && $this->embeddings->available();
    }

    /**
     * @param  Collection<int, ProductImage>  $images
     * @return array{embedded:int, failed:int, products: list<int>}
     */
    public function embed(Collection $images): array
    {
        $result = ['embedded' => 0, 'failed' => 0, 'products' => []];
        if (! $this->ready() || $images->isEmpty()) {
            return $result;
        }

        $sources = [];
        foreach ($images as $img) {
            $src = $this->source($img);
            if ($src === null) {
                $this->embeddings->markFailed($img->id);
                $result['failed']++;

                continue;
            }
            $sources[$img->id] = $src;
        }
        if (! $sources) {
            return $result;
        }

        try {
            $vectors = $this->clip->embedMany(array_values($sources));
        } catch (\Throwable $e) {
            Log::warning('Embedding batch failed', ['error' => $e->getMessage()]);
            $vectors = [];
        }

        foreach ($images as $img) {
            if (! isset($sources[$img->id])) {
                continue;
            }
            $vec = $vectors[$sources[$img->id]] ?? null;
            if ($vec) {
                $this->embeddings->save($img, $vec);
                $result['embedded']++;
                $result['products'][] = (int) $img->product_id;
            } else {
                $this->embeddings->markFailed($img->id);
                $result['failed']++;
            }
        }
        $result['products'] = array_values(array_unique($result['products']));

        return $result;
    }

    /**
     * What Replicate should fetch: the public https URL when APP_URL is a public
     * https site (vendor photos are public anyway), else a small data URI read
     * from our own storage (local dev, private disks, odd proxies).
     */
    public function source(ProductImage $img): ?string
    {
        $url = $img->url();
        if (! $url) {
            return null;
        }
        if (Str::startsWith($url, '/') && ! Str::startsWith($url, '//')) {
            $app = rtrim((string) config('app.url'), '/');
            $url = self::publicBase($app) ? $app.$url : null;
        }
        if ($url && Str::startsWith($url, 'https://') && self::publicBase($url)) {
            return $url;
        }

        return $this->dataUri($img);
    }

    private static function publicBase(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return str_starts_with($url, 'https://') && $host !== '' && ! in_array($host, ['localhost', '127.0.0.1', '0.0.0.0'], true)
            && ! str_ends_with($host, '.test') && ! str_ends_with($host, '.local');
    }

    private function dataUri(ProductImage $img): ?string
    {
        try {
            $disk = Storage::disk(config('neaby.uploads.disk', 'public'));
            if (! $img->path || ! $disk->exists($img->path)) {
                return null;
            }
            [$bytes, $mime] = self::shrink($disk->get($img->path));

            return $bytes ? 'data:'.$mime.';base64,'.base64_encode($bytes) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Downscale to a CLIP-friendly size (≤ 512 px, JPEG) so data URIs stay small.
     * CLIP looks at 224 px anyway. Without GD the original bytes are sent.
     *
     * @return array{0: ?string, 1: string}
     */
    public static function shrink(?string $bytes, int $max = 512): array
    {
        if (! $bytes) {
            return [null, 'image/jpeg'];
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'image/jpeg';
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            return [$bytes, $mime];
        }
        $src = @imagecreatefromstring($bytes);
        if (! $src) {
            return [$bytes, $mime];
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $k = min(1, $max / max($w, $h));
        $dst = imagecreatetruecolor(max(1, (int) round($w * $k)), max(1, (int) round($h * $k)));
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // transparent PNGs → white, not black
        imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
        ob_start();
        imagejpeg($dst, null, 85);
        $out = ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);

        return [$out ?: $bytes, $out ? 'image/jpeg' : $mime];
    }
}
