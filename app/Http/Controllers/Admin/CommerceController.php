<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Entitlement;
use App\Models\MembershipPlan;
use App\Models\Order;
use App\Models\ProductEvent;
use App\Models\Refund;
use App\Models\ResellerListing;
use App\Models\SponsoredPlacement;
use App\Models\Subscription;
use App\Models\SupplierRequest;
use App\Models\User;
use App\Services\Membership\MembershipService;
use App\Support\Present;
use App\Support\PresentCommerce;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Admin: memberships, analytics, refunds, sponsored placements. */
class CommerceController extends Controller
{
    public function plans()
    {
        $seo = Seo::make('Plans', index: false);

        return Inertia::render('Admin/Plans', [
            'plans' => MembershipPlan::query()->orderBy('sort')->get()->map(fn ($p) => PresentCommerce::plan($p) + [
                'subscribers' => Subscription::query()->where('membership_plan_id', $p->id)->where('status', 'active')->where('current_period_end', '>', now())->count(),
            ]),
            'keys' => Entitlement::KEYS,
            'grants' => Entitlement::query()->where('source_type', 'admin_grant')->with('user:id,username')->latest()->limit(20)->get()
                ->map(fn ($e) => ['user' => $e->user?->username, 'key' => $e->entitlement_key, 'status' => $e->status, 'expires' => $e->expires_at?->format('j M Y')]),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function updatePlan(Request $request, MembershipPlan $plan)
    {
        $data = $request->validate(['price' => ['required', 'numeric', 'min:0', 'max:100000000'], 'is_active' => ['required', 'boolean']]);
        $plan->update(['price_minor' => (int) round($data['price'] * 100), 'is_active' => $data['is_active']]);
        AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'plan.updated', 'subject_type' => MembershipPlan::class, 'subject_id' => $plan->id, 'meta' => $data]);

        return back()->with('success', 'Plan updated. Existing subscribers keep their current period.');
    }

    public function grant(Request $request, MembershipService $memberships)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:40'],
            'key' => ['required', Rule::in(Entitlement::KEYS)],
            'days' => ['required', 'integer', 'min:1', 'max:3650'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);
        $user = User::query()->where('username', ltrim($data['username'], '@'))->first();
        if (! $user) {
            return back()->withErrors(['username' => 'No user with that username.']);
        }
        $memberships->grant($user, $data['key'], $data['days'], $request->user(), $data['reason'] ?? null);

        return back()->with('success', "Granted {$data['key']} to @{$user->username}.");
    }

    public function analytics(Request $request)
    {
        $days = in_array((int) $request->query('days'), [7, 30, 90], true) ? (int) $request->query('days') : 30;
        $since = now()->subDays($days);
        $events = ProductEvent::query()->where('created_at', '>=', $since)->selectRaw('type, count(*) c')->groupBy('type')->pluck('c', 'type');
        $paid = Order::query()->where('created_at', '>=', $since)->whereNotIn('status', ['pending_payment', 'payment_failed', 'cancelled', 'refunded']);
        $series = ProductEvent::query()->where('created_at', '>=', $since)->whereIn('type', ['view', 'share', 'save', 'purchase'])
            ->selectRaw("to_char(created_at, 'YYYY-MM-DD') d, type, count(*) c")->groupBy('d', 'type')->orderBy('d')->get()
            ->groupBy('d')->map(fn ($g, $d) => ['date' => $d] + $g->pluck('c', 'type')->map(fn ($v) => (int) $v)->all())->values();
        $seo = Seo::make('Analytics', index: false);

        return Inertia::render('Admin/Analytics', [
            'days' => $days,
            'events' => $events,
            'series' => $series,
            'commerce' => [
                'orders' => (clone $paid)->count(),
                'gmv' => Present::money((int) (clone $paid)->sum('total_minor')),
                'refunds' => Refund::query()->where('created_at', '>=', $since)->count(),
                'pending_refunds' => Refund::query()->where('status', 'pending')->count(),
            ],
            'memberships' => Subscription::query()->where('status', 'active')->where('current_period_end', '>', now())
                ->selectRaw('membership_type, count(*) c')->groupBy('membership_type')->pluck('c', 'membership_type'),
            'membershipRevenue' => Present::money((int) DB::table('membership_payments')->where('status', 'paid')->where('created_at', '>=', $since)->sum('amount_minor')),
            'resale' => [
                'offers' => ResellerListing::query()->where('status', 'active')->count(),
                'requests_open' => SupplierRequest::query()->whereIn('status', SupplierRequest::OPEN)->count(),
                'requests_accepted' => SupplierRequest::query()->where('status', 'accepted')->count(),
            ],
            'sponsored' => [
                'live' => SponsoredPlacement::query()->live()->count(),
                'revenue' => Present::money((int) SponsoredPlacement::query()->whereIn('status', ['active', 'ended'])->where('created_at', '>=', $since)->sum('price_minor')),
            ],
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function refunds()
    {
        $seo = Seo::make('Refunds', index: false);

        return Inertia::render('Admin/Refunds', [
            'refunds' => Refund::query()->with('order:id,number,store_id')->latest()->limit(100)->get()->map(fn ($r) => [
                'id' => $r->id, 'order' => $r->order?->number, 'amount' => Present::money($r->amount_minor),
                'status' => $r->status, 'reason' => $r->reason, 'at' => $r->created_at?->format('j M Y, g:ia'),
            ]),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function updateRefund(Request $request, Refund $refund)
    {
        $status = $request->validate(['status' => ['required', Rule::in(['processed', 'rejected'])]])['status'];
        abort_unless($refund->status === 'pending', 422);
        $refund->update(['status' => $status, 'processed_at' => $status === 'processed' ? now() : null]);
        AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'refund.'.$status, 'subject_type' => Refund::class, 'subject_id' => $refund->id]);

        return back()->with('success', 'Refund marked '.$status.'.');
    }

    public function sponsored()
    {
        $seo = Seo::make('Sponsored', index: false);

        return Inertia::render('Admin/Sponsored', [
            'placements' => SponsoredPlacement::query()->with(['product:id,title,slug'])->latest()->limit(100)->get()->map(fn ($s) => [
                'id' => $s->id, 'title' => $s->product?->title, 'slug' => $s->product?->slug, 'status' => $s->status,
                'days' => $s->days, 'price' => Present::money($s->price_minor), 'starts' => $s->starts_at?->format('j M'), 'ends' => $s->ends_at?->format('j M'),
                'impressions' => $s->impressions, 'clicks' => $s->clicks,
            ]),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function updateSponsored(Request $request, SponsoredPlacement $placement)
    {
        $status = $request->validate(['status' => ['required', Rule::in(['rejected', 'ended'])]])['status'];
        $placement->update(['status' => $status, 'ends_at' => $placement->ends_at && $placement->ends_at->isFuture() ? now() : $placement->ends_at]);
        AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'sponsored.'.$status, 'subject_type' => SponsoredPlacement::class, 'subject_id' => $placement->id]);

        return back()->with('success', 'Placement '.$status.'.');
    }
}
