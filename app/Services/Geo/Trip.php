<?php

namespace App\Services\Geo;

use App\Support\NigeriaCities;
use Illuminate\Http\Request;

/**
 * Trip-aware shopping: rank around a destination city instead of the shopper's
 * current spot. Session for everyone, also saved on the account when signed in.
 * Clearing it puts normal GPS "near me" back exactly as before. No model involved.
 */
class Trip
{
    /** Canonical city name for loose input ("port harcourt", "PH", "abuja"), or null. */
    public static function city(string $input): ?array
    {
        $c = NigeriaCities::find($input);
        if (! $c) {
            return null;
        }
        $name = collect(array_keys(NigeriaCities::CITIES))->first(fn ($n) => NigeriaCities::CITIES[$n] === $c) ?? trim($input);

        return ['city' => $name, 'lat' => $c[0], 'lng' => $c[1], 'state' => $c[2]];
    }

    public static function set(Request $request, array $city): Near
    {
        $data = ['lat' => $city['lat'], 'lng' => $city['lng'], 'city' => $city['city'], 'state' => $city['state'], 'trip' => true];
        if ($request->hasSession()) {
            $request->session()->put(Near::TRIP_KEY, $data);
        }
        $request->user()?->forceFill([
            'trip_city' => $city['city'], 'trip_state' => $city['state'], 'trip_lat' => $city['lat'], 'trip_lng' => $city['lng'], 'trip_set_at' => now(),
        ])->save();
        Near::forget($request);

        return new Near($city['lat'], $city['lng'], $city['city'], $city['state'], true);
    }

    public static function clear(Request $request): void
    {
        if ($request->hasSession()) {
            $request->session()->put(Near::TRIP_KEY, ['off' => true]);
        }
        $request->user()?->forceFill(['trip_city' => null, 'trip_state' => null, 'trip_lat' => null, 'trip_lng' => null, 'trip_set_at' => null])->save();
        Near::forget($request);
    }

    /** @return list<string> */
    public static function cities(): array
    {
        $names = array_keys(NigeriaCities::CITIES);
        sort($names);

        return $names;
    }
}
