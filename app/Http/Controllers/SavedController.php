<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\SavedProduct;
use App\Services\Growth\Engagement;
use App\Support\Present;
use App\Support\Seo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SavedController extends Controller
{
    public function index(Request $request)
    {
        $saved = SavedProduct::query()->where('user_id', $request->user()->id)
            ->whereHas('product')
            ->with(['product' => fn ($q) => $q->with(['cover', 'store:id,name,slug,logo_path,accent,status', 'category:id,name,slug'])])
            ->latest()->paginate(36);
        $seo = Seo::make('Saved', index: false);

        return Inertia::render('Saved', [
            'products' => collect($saved->items())->map(fn ($s) => Present::productCard($s->product) + ['available_now' => $s->product->isPublic()])->values(),
            'next_page_url' => $saved->nextPageUrl(),
            'prev_page_url' => $saved->previousPageUrl(),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function toggle(Request $request, Product $product, Engagement $engagement)
    {
        $user = $request->user();
        $existing = SavedProduct::query()->where('user_id', $user->id)->where('product_id', $product->id)->first();
        if ($existing) {
            $existing->delete();
            Product::query()->whereKey($product->id)->where('saves_count', '>', 0)->decrement('saves_count');
            $saved = false;
        } else {
            abort_unless($product->isPublic(), 404);
            try {
                SavedProduct::create(['user_id' => $user->id, 'product_id' => $product->id]);
                $engagement->record($request, 'save', $product); // bumps saves_count (deduped)
            } catch (UniqueConstraintViolationException) {
            }
            $saved = true;
        }

        return $request->wantsJson()
            ? response()->json(['saved' => $saved])
            : back()->with('success', $saved ? 'Saved.' : 'Removed from saved.');
    }
}
