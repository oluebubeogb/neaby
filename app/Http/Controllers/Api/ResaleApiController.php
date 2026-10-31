<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Membership\Entitlements;
use App\Support\Present;
use Illuminate\Http\Request;

/** Protected resale terms. 403 (no data) unless the viewer is entitled. */
class ResaleApiController extends Controller
{
    public function show(Request $request, Product $product, Entitlements $entitlements)
    {
        $headers = ['Cache-Control' => 'private, no-store', 'Vary' => 'Cookie'];
        if (! $entitlements->canSeeResalePrice($request->user(), $product) || ! $product->resale_enabled) {
            return response()->json(['message' => 'Reseller Access is required to view resale terms.'], 403, $headers);
        }

        return response()->json(['product_id' => $product->id, 'resale' => Present::resaleTerms($product)], 200, $headers);
    }
}
