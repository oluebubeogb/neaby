<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Deep health check for Coolify / uptime monitors.
 * 200 when the database is reachable (and Redis, when configured); 503 otherwise.
 */
class HealthController extends Controller
{
    public function __invoke()
    {
        $checks = [];
        $ok = true;

        $checks['database'] = $this->probe(function () {
            DB::select('select 1');

            return DB::getDriverName();
        }, $ok);

        if ($this->redisConfigured()) {
            $checks['redis'] = $this->probe(function () {
                Redis::connection()->ping();

                return 'pong';
            }, $ok);
        } else {
            $checks['redis'] = ['ok' => true, 'detail' => 'not configured'];
        }

        // Storage problems are reported but don't fail the probe (reads still work).
        $checks['storage'] = $this->probe(function () {
            $disk = Storage::disk(config('neaby.uploads.disk', 'public'));
            $disk->put('.healthcheck', (string) now()->timestamp);

            return 'writable';
        }, $ignored);

        return response()->json([
            'status' => $ok ? 'ok' : 'degraded',
            'app' => config('app.name'),
            'time' => now()->toIso8601String(),
            'checks' => $checks,
        ], $ok ? 200 : 503, ['Cache-Control' => 'no-store']);
    }

    private function probe(callable $fn, &$ok): array
    {
        $ok ??= true;
        try {
            $started = microtime(true);
            $detail = $fn();

            return ['ok' => true, 'detail' => $detail, 'ms' => round((microtime(true) - $started) * 1000, 1)];
        } catch (Throwable $e) {
            $ok = false;
            report($e);

            return ['ok' => false, 'detail' => app()->hasDebugModeEnabled() ? $e->getMessage() : 'unavailable'];
        }
    }

    private function redisConfigured(): bool
    {
        $usesRedis = in_array('redis', [config('cache.default'), config('session.driver'), config('queue.default')], true);

        return $usesRedis || filled(env('REDIS_URL')) || filled(env('REDIS_HOST'));
    }
}
