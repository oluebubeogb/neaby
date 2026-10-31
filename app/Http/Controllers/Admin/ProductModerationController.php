<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Product;
use App\Support\Present;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProductModerationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->string('status')->toString() ?: 'pending';
        $q = trim($request->string('q')->toString());

        $products = Product::query()
            ->when($status !== 'all', fn ($w) => $w->where('status', $status))
            ->when($q !== '', fn ($w) => $w->where('title', 'ilike', '%'.$q.'%'))
            ->with(['cover', 'store:id,name,slug,logo_path,accent,status', 'category:id,name,slug', 'owner:id,username'])
            ->latest('updated_at')->paginate(30)->withQueryString();

        $seo = Seo::make('Moderation', index: false);

        return Inertia::render('Admin/Products', [
            'products' => [
                'data' => collect($products->items())->map(fn (Product $p) => Present::productCard($p) + [
                    'description' => Present::excerpt($p->description, 240),
                    'owner' => $p->owner?->username,
                    'moderation_note' => $p->moderation_note,
                    'updated_at' => $p->updated_at?->diffForHumans(),
                ]),
                'next_page_url' => $products->nextPageUrl(),
                'prev_page_url' => $products->previousPageUrl(),
                'total' => $products->total(),
            ],
            'filters' => ['status' => $status, 'q' => $q],
            'statuses' => array_merge(['all'], Product::STATUSES),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject', 'unpublish'])],
            'reason' => ['nullable', 'required_if:action,reject', 'string', 'max:500'],
        ], ['reason.required_if' => 'Give the seller a reason so they can fix it.']);

        match ($data['action']) {
            'approve' => $product->forceFill([
                'status' => 'published',
                'moderation_note' => null,
                'published_at' => $product->published_at ?? now(),
            ]),
            'reject' => $product->forceFill(['status' => 'rejected', 'moderation_note' => $data['reason']]),
            'unpublish' => $product->forceFill(['status' => 'rejected', 'moderation_note' => $data['reason'] ?: 'Removed by moderation.']),
        };
        $product->save();

        AuditLog::record('product.'.$data['action'], $product, $data['reason'] ?? null);

        return back()->with('success', "“{$product->title}” → {$product->status}.");
    }
}
