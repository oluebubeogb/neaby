<?php

namespace App\Providers;

use App\Contracts\ListingAi;
use App\Contracts\ObjectStore;
use App\Contracts\PaymentProvider;
use App\Models\User;
use App\Services\Ai\FakeListingAi;
use App\Services\Ai\NullListingAi;
use App\Services\Ai\OpenAiCompatibleListingAi;
use App\Services\Payments\PaymentManager;
use App\Services\Storage\LocalObjectStore;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ObjectStore::class, fn () => new LocalObjectStore(config('neaby.uploads.disk', 'public')));

        $this->app->singleton(ListingAi::class, function () {
            $cfg = config('neaby.ai');

            return match ($cfg['driver'] ?? 'fake') {
                'openai' => new OpenAiCompatibleListingAi($cfg, new FakeListingAi),
                'null', 'none', 'off' => new NullListingAi,
                default => new FakeListingAi,
            };
        });

        $this->app->singleton(PaymentManager::class);
        $this->app->bind(PaymentProvider::class, fn ($app) => $app->make(PaymentManager::class)->provider());
        // Per-request memo of entitlement keys (Octane-safe).
        $this->app->scoped(\App\Services\Membership\Entitlements::class);
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Behind Coolify/Traefik TLS: generate https URLs when APP_URL is https.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Gate::define('admin', fn (User $user) => $user->isAdmin());

        RateLimiter::for('ai', fn (Request $r) => Limit::perMinute(12)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('uploads', fn (Request $r) => Limit::perMinute(30)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('webhooks', fn (Request $r) => Limit::perMinute(120)->by($r->ip()));
        RateLimiter::for('events', fn (Request $r) => Limit::perMinute(60)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('shares', fn (Request $r) => Limit::perMinute(90)->by($r->ip()));
        RateLimiter::for('checkout', fn (Request $r) => Limit::perMinute(10)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('requests', fn (Request $r) => Limit::perHour(20)->by($r->user()?->id ?: $r->ip()));
    }
}
