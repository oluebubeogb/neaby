<?php

namespace App\Services\Seller;

use App\Models\Product;
use App\Support\Present;

/**
 * Price guidance from comparable live listings (same category, same condition).
 * Shows a range and where the seller sits — advice, never auto-applied.
 */
class PricingAssistant
{
    public function suggest(?int $categoryId, ?string $condition, ?int $priceMinor = null, ?int $excludeId = null): array
    {
        if (! $categoryId) {
            return ['available' => false, 'message' => 'Pick a category to see price guidance.'];
        }
        $prices = Product::query()->published()->where('category_id', $categoryId)
            ->when($condition, fn ($q) => $q->where('condition', $condition))
            ->when($excludeId, fn ($q) => $q->whereKeyNot($excludeId))
            ->where('price_minor', '>', 0)->orderBy('price_minor')->limit(500)->pluck('price_minor')->all();

        $n = count($prices);
        if ($n < 3) {
            return ['available' => false, 'sample' => $n, 'message' => 'Not enough similar listings yet for guidance.'];
        }
        $q = fn (float $p) => $prices[(int) floor(($n - 1) * $p)];
        $p25 = $q(0.25);
        $median = $q(0.5);
        $p75 = $q(0.75);

        $position = null;
        $tip = 'Similar items usually sell between '.Present::money($p25).' and '.Present::money($p75).'.';
        if ($priceMinor) {
            $position = $priceMinor < $p25 ? 'below' : ($priceMinor > $p75 ? 'above' : 'within');
            $tip .= match ($position) {
                'below' => ' Your price is below most — buyers may ask why, or you may be leaving money on the table.',
                'above' => ' Your price is above most — great photos and a detailed description help justify it.',
                default => ' Your price is in the typical range.',
            };
        }

        return [
            'available' => true,
            'sample' => $n,
            'low' => Present::money($p25), 'median' => Present::money($median), 'high' => Present::money($p75),
            'low_minor' => $p25, 'median_minor' => $median, 'high_minor' => $p75,
            'position' => $position,
            'message' => $tip,
        ];
    }
}
