<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Store;
use App\Support\Present;
use App\Support\Seo;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LandingController extends Controller
{
    public function __invoke(Request $request)
    {
        if ($request->user()) {
            return redirect()->route('home');
        }

        $products = Product::query()->published()
            ->with(['cover', 'store:id,name,slug,logo_path,accent,status'])
            ->latest('published_at')->limit(8)->get();

        $seo = Seo::make('neaby', 'Discover products from stores near you. Open a free storefront, list in minutes with AI-written titles, and share anywhere.');

        return Inertia::render('Landing', [
            'products' => $products->map(fn ($p) => Present::productCard($p)),
            'stats' => [
                'products' => Product::query()->published()->count(),
                'stores' => Store::query()->active()->count(),
            ],
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }
}
