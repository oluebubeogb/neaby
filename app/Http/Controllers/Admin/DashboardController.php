<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Support\Seo;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $seo = Seo::make('Admin', index: false);

        return Inertia::render('Admin/Index', [
            'stats' => [
                'users' => User::count(),
                'stores' => Store::count(),
                'products' => Product::count(),
                'published' => Product::where('status', 'published')->count(),
                'pending' => Product::where('status', 'pending')->count(),
                'rejected' => Product::where('status', 'rejected')->count(),
            ],
            'recent' => AuditLog::query()->with('actor:id,username')->latest('created_at')->limit(15)->get()
                ->map(fn (AuditLog $l) => [
                    'id' => $l->id,
                    'action' => $l->action,
                    'actor' => $l->actor?->username,
                    'subject' => class_basename((string) $l->subject_type).' #'.$l->subject_id,
                    'reason' => $l->reason,
                    'at' => $l->created_at?->diffForHumans(),
                ]),
            'reviewRequired' => (bool) config('neaby.require_product_review'),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }
}
