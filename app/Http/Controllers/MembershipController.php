<?php

namespace App\Http\Controllers;

use App\Models\MembershipPlan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Membership\Entitlements;
use App\Services\Membership\MembershipService;
use App\Support\PresentCommerce;
use App\Support\Seo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;

class MembershipController extends Controller
{
    public function __construct(private readonly MembershipService $memberships, private readonly Entitlements $entitlements) {}

    public function index(Request $request)
    {
        $user = $request->user();
        if ($request->filled('ref') && ($ref = User::query()->where('username', ltrim((string) $request->query('ref'), '@'))->first()) && $ref->id !== $user->id) {
            $request->session()->put('membership_ref', $ref->id);
        }
        $plans = MembershipPlan::query()->active()->orderBy('sort')->get();
        $seo = Seo::make('Membership', index: false);

        return Inertia::render('Membership', [
            'plans' => $plans->map(fn ($p) => PresentCommerce::plan($p))->groupBy('type'),
            'current' => collect(MembershipPlan::TYPES)->mapWithKeys(fn ($t) => [$t => PresentCommerce::subscription($this->memberships->currentSubscription($user, $t))]),
            'entitlements' => $this->entitlements->keys($user),
            'history' => Subscription::query()->with('plan')->where('user_id', $user->id)->latest()->limit(10)->get()->map(fn ($s) => PresentCommerce::subscription($s)),
            'hasStore' => (bool) $user->store,
            'sellerProEnabled' => (bool) config('neaby.seller_pro.enabled'),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function purchase(Request $request, MembershipPlan $plan)
    {
        abort_unless($plan->is_active, 404);
        if ($plan->membership_type === 'supplier_resale' && ! $request->user()->store) {
            return back()->with('error', 'Open a store first — Supplier Resale Access is for sellers with their own stock.');
        }
        try {
            $result = $this->memberships->purchase($request->user(), $plan, $request->session()->pull('membership_ref'));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return Inertia::location($result['checkout_url']);
    }

    public function cancel(Request $request, Subscription $subscription)
    {
        abort_unless($subscription->user_id === $request->user()->id, 404);
        $this->memberships->cancel($subscription, $request->user());

        return back()->with('success', 'Membership cancelled. You keep access until '.$subscription->fresh()->current_period_end?->format('j M Y').'. Accepted supplier commitments are not affected.');
    }
}
