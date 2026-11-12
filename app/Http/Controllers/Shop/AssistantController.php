<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\Ai\ShopperAi;
use App\Services\Geo\Near;
use App\Services\Shopping\GiftIdeas;
use App\Services\Shopping\ShoppingSearch;
use App\Services\Shopping\VoiceAssistant;
use App\Services\Wallet\WalletService;
use App\Support\Present;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Gift mode, "what can I get with my balance", and the voice "shop with me"
 * session. Each makes at most ONE model call, only when the shopper asks; the
 * results are always real in-stock listings from the catalogue.
 */
class AssistantController extends Controller
{
    public function gift(Request $request, WalletService $wallets)
    {
        $seo = Seo::make('Gift ideas', 'Find a gift from stores near you on neaby.', index: false);

        return Inertia::render('Gift', [
            'occasions' => GiftIdeas::OCCASIONS,
            'recipients' => GiftIdeas::RECIPIENTS,
            'wallet' => $this->wallet($request, $wallets),
            'seo' => $seo,
        ])->withViewData(['seo' => $seo]);
    }

    public function giftSuggest(Request $request, GiftIdeas $gifts, ShoppingSearch $search)
    {
        $data = $request->validate([
            'occasion' => ['nullable', 'string', 'max:40'],
            'recipient' => ['nullable', Rule::in(GiftIdeas::RECIPIENTS)],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);
        $budget = ! empty($data['budget']) ? (int) round($data['budget'] * 100) : null;
        $plan = $gifts->plan($data['occasion'] ?? null, $data['recipient'] ?? null, $budget, $data['note'] ?? null);
        $items = $search->forQueries($plan['queries'], $budget, 12, excludeUserId: $request->user()?->id);
        if ($items->isEmpty() && $plan['ai']) {
            // Model ideas found nothing in stock → try the offline ideas before giving up.
            $items = $search->forQueries($gifts->rules($data['occasion'] ?? null, $data['recipient'] ?? null, $data['note'] ?? null), $budget, 12, excludeUserId: $request->user()?->id);
        }

        return response()->json([
            'items' => $items->map(fn ($p) => Present::productCard($p))->values(),
            'ideas' => $plan['queries'],
            'note' => $plan['note'],
            'ai' => $plan['ai'],
            'nearby' => (bool) Near::current(),
        ])->header('Cache-Control', 'private, no-store');
    }

    /** "What can I get?" — in-stock listings at or under the wallet balance. One optional model call for a wish. */
    public function budget(Request $request, WalletService $wallets, ShopperAi $ai, ShoppingSearch $search)
    {
        abort_unless(config('neaby.wallet.enabled', true), 404);
        $data = $request->validate(['wish' => ['nullable', 'string', 'max:200']]);
        $user = $request->user();
        $balance = $wallets->for($user)->available();
        if ($balance <= 0) {
            return response()->json(['items' => [], 'balance' => Present::money(0), 'message' => 'Your wallet is empty — top up to shop with your balance.']);
        }
        $wish = trim((string) ($data['wish'] ?? ''));
        $ideas = [];
        $usedAi = false;
        if ($wish !== '') {
            $plan = $ai->enabled() ? $ai->plan(['task' => 'budget', 'wish' => mb_substr($wish, 0, 200), 'budget_naira' => (int) floor($balance / 100)]) : null;
            $usedAi = (bool) $plan;
            $ideas = $plan['queries'] ?? [\App\Services\Shopping\QueryHints::keywords($wish) ?: $wish];
        }
        $items = $ideas ? $search->forQueries($ideas, $balance, 12, excludeUserId: $user->id) : collect();
        if ($items->isEmpty()) {
            $items = $search->underBudget($balance, 12, $user->id);
        }

        return response()->json([
            'items' => $items->map(fn ($p) => Present::productCard($p))->values(),
            'balance' => Present::money($balance),
            'ideas' => $ideas,
            'ai' => $usedAi,
            'message' => $items->isEmpty() ? 'Nothing in stock under your balance yet.' : null,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function voice(Request $request, VoiceAssistant $assistant)
    {
        $data = $request->validate(['text' => ['required', 'string', 'min:1', 'max:400']]);

        return response()->json($assistant->handle($request, $data['text']))->header('Cache-Control', 'private, no-store');
    }

    private function wallet(Request $request, WalletService $wallets): ?array
    {
        $u = $request->user();
        if (! $u || ! config('neaby.wallet.enabled', true)) {
            return null;
        }
        $a = $wallets->for($u)->available();

        return ['available_minor' => $a, 'available' => Present::money($a)];
    }
}
