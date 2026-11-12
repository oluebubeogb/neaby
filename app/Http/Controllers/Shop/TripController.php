<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\Geo\Trip;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Trip destination for trip-aware ranking (session + account). No model involved. */
class TripController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate(['city' => ['required', 'string', 'max:80']]);
        $city = Trip::city($data['city']);
        if (! $city) {
            throw ValidationException::withMessages(['city' => 'Pick a city from the list.']);
        }
        $near = Trip::set($request, $city);

        return response()->json(['city' => $near->city, 'state' => $near->state, 'trip' => true]);
    }

    public function destroy(Request $request)
    {
        Trip::clear($request);

        return response()->noContent();
    }

    public function cities()
    {
        return response()->json(['cities' => Trip::cities()])->header('Cache-Control', 'public, max-age=86400');
    }
}
