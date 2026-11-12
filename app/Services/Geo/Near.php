<?php

namespace App\Services\Geo;

use App\Support\NigeriaCities;
use Illuminate\Http\Request;

/**
 * Where the shopper is: precise coords (browser geolocation, kept in the session
 * and on the user) and/or a city/state (from coords, or the profile). Never required.
 */
final class Near
{
    public const SESSION_KEY = 'neaby.near';

    public function __construct(
        public readonly ?float $lat = null,
        public readonly ?float $lng = null,
        public readonly ?string $city = null,
        public readonly ?string $state = null,
    ) {}

    /** The current request's shopper location (memoised on the request). */
    public static function current(): ?self
    {
        if (! app()->bound('request')) {
            return null;
        }
        $request = request();
        if (! $request->attributes->has(self::SESSION_KEY)) {
            $request->attributes->set(self::SESSION_KEY, self::fromRequest($request));
        }

        return $request->attributes->get(self::SESSION_KEY);
    }

    public static function forget(Request $request): void
    {
        $request->attributes->remove(self::SESSION_KEY);
    }

    public static function fromRequest(Request $request): ?self
    {
        $s = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;
        if (is_array($s) && isset($s['lat'], $s['lng'])) {
            return new self((float) $s['lat'], (float) $s['lng'], $s['city'] ?? null, $s['state'] ?? null);
        }
        $u = $request->user();
        if ($u && $u->location_lat !== null && $u->location_lng !== null) {
            return self::fromCoords((float) $u->location_lat, (float) $u->location_lng);
        }
        if ($u && ($u->city || $u->state)) {
            $c = NigeriaCities::find($u->city);

            return new self($c[0] ?? null, $c[1] ?? null, $u->city, $u->state ?: ($c[2] ?? null));
        }

        return null;
    }

    public static function fromCoords(float $lat, float $lng): self
    {
        $c = NigeriaCities::nearest($lat, $lng);

        return new self($lat, $lng, $c['city'] ?? null, $c['state'] ?? null);
    }

    public function hasCoords(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }

    public function toArray(): array
    {
        return ['lat' => $this->lat, 'lng' => $this->lng, 'city' => $this->city, 'state' => $this->state];
    }
}
