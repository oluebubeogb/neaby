<?php

namespace App\Http\Controllers\Sell;

use App\Contracts\ListingAi;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function titles(Request $request, ListingAi $ai)
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'min:4', 'max:5000'],
            'category_id' => ['nullable', 'integer'],
            'condition' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:80'],
        ]);

        if (! $ai->enabled()) {
            return response()->json(['titles' => [], 'enabled' => false]);
        }

        $titles = $ai->suggestTitles([
            'description' => $data['description'],
            'category' => ! empty($data['category_id']) ? Category::find($data['category_id'])?->name : null,
            'condition' => $data['condition'] ?? null,
            'city' => $data['city'] ?? null,
        ]);

        return response()->json(['titles' => array_values($titles), 'enabled' => true]);
    }

    public function description(Request $request, ListingAi $ai)
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:140'],
            'notes' => ['required', 'string', 'min:4', 'max:5000'],
            'category_id' => ['nullable', 'integer'],
            'condition' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:80'],
        ]);
        if (! $ai->enabled()) {
            return response()->json(['description' => null, 'enabled' => false]);
        }

        return response()->json(['enabled' => true, 'description' => $ai->suggestDescription([
            'title' => $data['title'] ?? null,
            'notes' => $data['notes'],
            'category' => ! empty($data['category_id']) ? Category::find($data['category_id'])?->name : null,
            'condition' => $data['condition'] ?? null,
            'city' => $data['city'] ?? null,
        ])]);
    }

    public function pricing(Request $request, \App\Services\Seller\PricingAssistant $assistant)
    {
        $data = $request->validate([
            'category_id' => ['nullable', 'integer'],
            'condition' => ['nullable', 'string', 'max:20'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'product_id' => ['nullable', 'integer'],
        ]);

        return response()->json($assistant->suggest(
            $data['category_id'] ?? null,
            $data['condition'] ?? null,
            isset($data['price']) ? (int) round($data['price'] * 100) : null,
            $data['product_id'] ?? null,
        ));
    }
}
