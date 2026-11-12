<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Replicate CLIP image embeddings (clip-vit-large-patch14 → 768 floats).
 * One model for vendor listing photos AND shopper snaps — vectors from
 * different models are never compared (see embedding_model on product_images).
 *
 * The model takes a newline-separated list of image URLs / data URIs and returns
 * [{input, embedding}]. Vectors are L2-normalised here, so cosine == dot product.
 * Every failure returns null / [] and logs — callers fall back calmly.
 */
class ReplicateClip
{
    /** Only version of andreasjansson/clip-features (community model → needs a version id). */
    public const KNOWN_VERSIONS = [
        'andreasjansson/clip-features' => '75b33f253f7714a281ad3e9b28f63e3232d583716ef6718f2e46641077ea040a',
    ];

    public function __construct(private readonly array $config = []) {}

    public static function make(): self
    {
        return new self((array) config('neaby.clip', []));
    }

    /** Cheap config check — never touches the network (safe for shared Inertia props). */
    public function enabled(): bool
    {
        return filled($this->config['token'] ?? null);
    }

    public function model(): string
    {
        return (string) ($this->config['model'] ?? 'andreasjansson/clip-features');
    }

    public function dimensions(): int
    {
        return (int) ($this->config['dimensions'] ?? 768);
    }

    /** @return list<float>|null */
    public function embedUrl(string $url): ?array
    {
        return $this->embedMany([$url])[$url] ?? null;
    }

    /** Raw image bytes (shopper upload) → data URI, so Replicate needs no public URL. */
    public function embedBytes(string $bytes, string $mime = 'image/jpeg'): ?array
    {
        $uri = 'data:'.$mime.';base64,'.base64_encode($bytes);

        return $this->embedMany([$uri])[$uri] ?? null;
    }

    /**
     * @param  list<string>  $inputs  image URLs or data URIs
     * @return array<string, list<float>> input → unit vector (missing = failed)
     */
    public function embedMany(array $inputs): array
    {
        $inputs = array_values(array_unique(array_filter(array_map('trim', $inputs))));
        if (! $this->enabled() || ! $inputs) {
            return [];
        }

        try {
            $prediction = $this->predict(implode("\n", $inputs));
        } catch (Throwable $e) {
            Log::warning('Replicate CLIP failed', ['error' => $e->getMessage()]);

            return [];
        }
        $output = $prediction['output'] ?? null;
        if (! is_array($output)) {
            Log::warning('Replicate CLIP: no output', ['status' => $prediction['status'] ?? null, 'error' => $prediction['error'] ?? null]);

            return [];
        }

        $out = [];
        foreach ($output as $i => $row) {
            $vec = is_array($row) ? ($row['embedding'] ?? null) : null;
            $key = is_array($row) && isset($row['input']) ? (string) $row['input'] : ($inputs[$i] ?? null);
            // The model echoes inputs back; map by position when the echo differs (e.g. trimmed data URIs).
            if ($key !== null && ! in_array($key, $inputs, true)) {
                $key = $inputs[$i] ?? null;
            }
            if ($key === null || ! is_array($vec) || count($vec) !== $this->dimensions()) {
                continue;
            }
            if ($unit = self::normalise($vec)) {
                $out[$key] = $unit;
            }
        }

        return $out;
    }

    /** @return list<float>|null */
    public static function normalise(array $vec): ?array
    {
        $vec = array_map('floatval', array_values($vec));
        $norm = sqrt(array_sum(array_map(fn ($v) => $v * $v, $vec)));
        if (! is_finite($norm) || $norm <= 0.0) {
            return null;
        }

        return array_map(fn ($v) => $v / $norm, $vec);
    }

    private function http()
    {
        return Http::baseUrl(rtrim((string) ($this->config['base_url'] ?? 'https://api.replicate.com/v1'), '/'))
            ->withToken((string) $this->config['token'])
            ->acceptJson()
            ->timeout(max(5, (int) ($this->config['timeout'] ?? 60)))
            ->connectTimeout(10);
    }

    /** Create a prediction (Prefer: wait) and poll briefly if it is still running. */
    private function predict(string $inputs): array
    {
        $version = $this->version();
        $body = ['input' => ['inputs' => $inputs]];
        $wait = ['Prefer' => 'wait'];

        $res = $version
            ? $this->http()->withHeaders($wait)->post('/predictions', $body + ['version' => $version])
            : $this->http()->withHeaders($wait)->post('/models/'.$this->model().'/predictions', $body);

        // Stale cached version → resolve again once.
        if ($version && in_array($res->status(), [404, 422], true) && blank($this->config['version'] ?? null)) {
            Cache::forget($this->versionCacheKey());
            if (($fresh = $this->version(refresh: true)) && $fresh !== $version) {
                $res = $this->http()->withHeaders($wait)->post('/predictions', $body + ['version' => $fresh]);
            }
        }
        $res->throw();
        $prediction = (array) $res->json();

        $deadline = microtime(true) + max(5, (int) ($this->config['timeout'] ?? 60));
        while (in_array($prediction['status'] ?? null, ['starting', 'processing'], true) && microtime(true) < $deadline) {
            $get = $prediction['urls']['get'] ?? (isset($prediction['id']) ? '/predictions/'.$prediction['id'] : null);
            if (! $get) {
                break;
            }
            usleep(800_000);
            $prediction = (array) $this->http()->get($get)->throw()->json();
        }

        if (($prediction['status'] ?? null) !== 'succeeded') {
            throw new \RuntimeException('prediction '.($prediction['status'] ?? 'unknown').': '.json_encode($prediction['error'] ?? null));
        }

        return $prediction;
    }

    private function versionCacheKey(): string
    {
        return 'replicate:version:'.$this->model();
    }

    /** Explicit REPLICATE_CLIP_VERSION → model's latest version (cached a day) → known default. */
    private function version(bool $refresh = false): ?string
    {
        if (filled($this->config['version'] ?? null)) {
            return (string) $this->config['version'];
        }
        $model = $this->model();
        $resolve = function () use ($model) {
            try {
                $id = $this->http()->timeout(15)->get('/models/'.$model)->json('latest_version.id');

                return is_string($id) && $id !== '' ? $id : null;
            } catch (Throwable) {
                return null;
            }
        };
        if (! $refresh && ($cached = Cache::get($this->versionCacheKey()))) {
            return $cached;
        }
        $id = self::KNOWN_VERSIONS[$model] ?? null;
        if ($refresh || ! $id) {
            $id = $resolve() ?? $id;
        }
        if ($id) {
            Cache::put($this->versionCacheKey(), $id, now()->addDay());
        }

        return $id;
    }
}
