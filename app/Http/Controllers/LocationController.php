<?php

namespace App\Http\Controllers;

use App\Services\Geo\Near;
use App\Support\NigeriaCities;
use Illuminate\Http\Request;

/**
 * Shopper location (browser geolocation, only ever sent after the shopper taps
 * "near me"). Kept in the session — and on the account when signed in — so the
 * feed and search can show nearby stock first. Optional; nothing depends on it.
 */
class LocationController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            // Generous box around Nigeria; elsewhere we simply don't rank by distance.
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);
        $lat = round((float) $data['lat'], 5);
        $lng = round((float) $data['lng'], 5);
        $near = Near::fromCoords($lat, $lng);

        $request->session()->put(Near::SESSION_KEY, $near->toArray());
        Near::forget($request);
        if ($user = $request->user()) {
            $user->forceFill(['location_lat' => $lat, 'location_lng' => $lng, 'location_updated_at' => now()])->save();
        }

        return response()->json(['city' => $near->city, 'state' => $near->state]);
    }

    /** Nearest town for a GPS fix (offline lookup, no paid geocoder) — fills city/state on store setup. */
    public function area(Request $request)
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);
        $c = NigeriaCities::nearest((float) $data['lat'], (float) $data['lng']);

        return response()->json(['city' => $c['city'] ?? null, 'state' => $c['state'] ?? null]);
    }

    public function destroy(Request $request)
    {
        $request->session()->forget(Near::SESSION_KEY);
        Near::forget($request);
        $request->user()?->forceFill(['location_lat' => null, 'location_lng' => null, 'location_updated_at' => null])->save();

        return response()->noContent();
    }
}
