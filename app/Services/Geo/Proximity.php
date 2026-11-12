<?php

namespace App\Services\Geo;

use App\Models\Store;
use App\Support\NigeriaCities;

/**
 * Nearby-first ranking + human travel labels.
 *
 * Distance per store: its confirmed GPS pin when it has one, else the centre of
 * its city (NigeriaCities), else "same state", else unknown (ranked last but
 * still shown). Ranking uses coarse tiers so relevance/freshness still matter
 * inside a tier: same area → nearby towns → region → far → unknown.
 */
class Proximity
{
    /** Tier upper bounds in km (tier index = position). Beyond the last → FAR; no location → UNKNOWN. */
    public const TIERS = [25, 80, 200, 450];

    public const FAR = 4;

    public const UNKNOWN = 5;

    private const ROAD_FACTOR = 1.3;   // straight line → road distance

    private const KM_PER_DEG = 111.32;

    public static function km(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * asin(min(1.0, sqrt($a)));
    }

    public static function tierFor(?float $km): int
    {
        if ($km === null) {
            return self::UNKNOWN;
        }
        foreach (self::TIERS as $i => $max) {
            if ($km <= $max) {
                return $i;
            }
        }

        return self::FAR;
    }

    /** Best-effort km from the shopper to a store (GPS → city centre → same state). */
    public static function storeKm(?Near $near, ?Store $store, ?string $fallbackCity = null, ?string $fallbackState = null): ?float
    {
        if (! $near) {
            return null;
        }
        $lat = $store?->lat;
        $lng = $store?->lng;
        if (($lat === null || $lng === null) && ($c = NigeriaCities::find($store?->city ?: $fallbackCity))) {
            [$lat, $lng] = $c;
        }
        if ($lat !== null && $lng !== null && $near->hasCoords()) {
            return self::km($near->lat, $near->lng, (float) $lat, (float) $lng);
        }
        $city = $store?->city ?: $fallbackCity;
        if ($near->city && $city && NigeriaCities::key($near->city) === NigeriaCities::key($city)) {
            return 5.0;
        }
        $state = $store?->state ?: $fallbackState;
        if ($near->state && $state && mb_strtolower($near->state) === mb_strtolower($state)) {
            return 120.0;
        }

        return null;
    }

    /**
     * SQL expression (+ bindings) giving the proximity tier (0 = closest … 5 = unknown)
     * for a products query. Portable across Postgres/SQLite/MySQL: squared
     * equirectangular km (no trig in SQL), plus a CASE over known city centres.
     *
     * @return array{0: string, 1: array}
     */
    public static function tierSql(Near $near, string $storeAlias = 'geo_store'): array
    {
        $cityExpr = "lower(coalesce({$storeAlias}.city, products.city, ''))";
        $stateExpr = "lower(coalesce({$storeAlias}.state, products.state, ''))";
        $branches = [];
        $bindings = [];

        if ($near->hasCoords()) {
            $cos = cos(deg2rad($near->lat));
            $dLat = "(({$storeAlias}.lat - ?) * ".self::KM_PER_DEG.')';
            $dLng = "(({$storeAlias}.lng - ?) * ".(self::KM_PER_DEG * $cos).')';
            $d2 = "({$dLat} * {$dLat} + {$dLng} * {$dLng})";
            $d2Bind = [$near->lat, $near->lat, $near->lng, $near->lng];
            $inner = [];
            foreach (self::TIERS as $i => $max) {
                $inner[] = "WHEN {$d2} <= ".($max * $max)." THEN {$i}";
                array_push($bindings, ...$d2Bind);
            }
            $branches[] = "WHEN {$storeAlias}.lat IS NOT NULL AND {$storeAlias}.lng IS NOT NULL THEN CASE ".implode(' ', $inner).' ELSE '.self::FAR.' END';

            // Stores with only a city: distance between the shopper and that city's centre.
            $byTier = array_fill_keys(range(0, self::FAR), []);
            foreach (NigeriaCities::CITIES as $name => [$clat, $clng]) {
                $t = self::tierFor(self::km($near->lat, $near->lng, $clat, $clng));
                array_push($byTier[$t], ...array_unique([mb_strtolower($name), NigeriaCities::key($name)]));
            }
            foreach (array_filter($byTier) as $tier => $keys) {
                $branches[] = "WHEN {$cityExpr} IN (".implode(',', array_fill(0, count($keys), '?')).") THEN {$tier}";
                array_push($bindings, ...$keys);
            }
        }
        if ($near->city) {
            $branches[] = "WHEN {$cityExpr} IN (?, ?) THEN 0";
            array_push($bindings, mb_strtolower($near->city), NigeriaCities::key($near->city));
        }
        if ($near->state) {
            $branches[] = "WHEN {$stateExpr} = ? THEN 2";
            $bindings[] = mb_strtolower($near->state);
        }
        if (! $branches) {
            return [(string) self::UNKNOWN, []];
        }

        return ['(CASE '.implode(' ', $branches).' ELSE '.self::UNKNOWN.' END)', $bindings];
    }

    /**
     * Human travel label for a GPS-pinned store: "4 min walk", "12 min drive",
     * "About 2 hr away", or the city for long trips. Null when we can't say honestly.
     */
    public static function label(?Near $near, ?Store $store): ?string
    {
        if (! $near?->hasCoords() || ! $store || $store->lat === null || $store->lng === null) {
            return null;
        }
        $road = self::km($near->lat, $near->lng, (float) $store->lat, (float) $store->lng) * self::ROAD_FACTOR;

        if ($road <= 1.2) {
            return max(1, (int) round($road / 5 * 60)).' min walk';          // ~5 km/h on foot
        }
        $speed = match (true) {                                               // urban traffic → open road
            $road < 10 => 25,
            $road < 40 => 35,
            $road < 150 => 55,
            default => 70,
        };
        $minutes = $road / $speed * 60;
        if ($minutes < 60) {
            return max(2, (int) (ceil($minutes / ($minutes < 20 ? 1 : 5)) * ($minutes < 20 ? 1 : 5))).' min drive';
        }
        if ($minutes <= 240) {
            $hours = round($minutes / 30) / 2;                                // nearest half hour

            return 'About '.rtrim(rtrim(number_format($hours, 1), '0'), '.').' hr away';
        }

        return $store->city ? 'In '.$store->city : null;
    }
}
