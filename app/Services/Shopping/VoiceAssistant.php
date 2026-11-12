<?php

namespace App\Services\Shopping;

use App\Models\Product;
use App\Services\Ai\ShopperAi;
use App\Services\Commerce\CheckoutService;
use App\Services\Geo\Near;
use App\Services\Geo\Trip;
use App\Services\Growth\Engagement;
use App\Services\Wallet\WalletService;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * "Shop with me": the shopper speaks (or types), RunPod picks whitelisted actions,
 * the server runs them with the SAME search / cart / trip code as the rest of the
 * site and returns real listings. No AI → simple offline intent rules, same actions.
 * The last results live in the server session, so "add the second one" can only
 * ever point at a listing we just showed this shopper.
 */
class VoiceAssistant
{
    public const SESSION_LAST = 'neaby.voice.last';

    private const MAX_RESULTS = 8;

    public function __construct(
        private readonly ShopperAi $ai,
        private readonly ShoppingSearch $search,
        private readonly GiftIdeas $gifts,
        private readonly CheckoutService $checkout,
        private readonly Engagement $engagement,
    ) {}

    /**
     * @return array{reply: string, items: list<array>, cart_added: ?array, client: list<string>, ai: bool, open: ?string}
     */
    public function handle(Request $request, string $text): array
    {
        $text = trim(mb_substr($text, 0, 400));
        $user = $request->user();
        $last = $this->lastResults($request);

        $context = [
            'last' => $last->values()->map(fn (Product $p, $i) => ['n' => $i + 1, 'title' => $p->title])->all(),
            'signed_in' => (bool) $user,
            'near' => ($n = Near::current()) ? ['city' => $n->city, 'trip' => $n->trip] : null,
        ];
        $plan = $this->ai->instruct($text, $context);
        $usedAi = (bool) $plan;
        $plan ??= $this->rules($text);
        if (! $plan['actions']) {
            $plan['actions'] = [['type' => 'search', 'query' => QueryHints::keywords($text) ?: $text]];
        }

        $out = ['reply' => $plan['reply'] ?? null, 'items' => null, 'cart_added' => null, 'client' => [], 'ai' => $usedAi, 'open' => null];
        $fallbackReply = null;

        foreach (array_slice($plan['actions'], 0, 2) as $a) {
            $type = (string) ($a['type'] ?? '');
            switch ($type) {
                case 'search':
                    $q = trim((string) ($a['query'] ?? ''));
                    $q = $q !== '' ? $q : QueryHints::keywords($text);
                    $max = self::minor($a['max'] ?? null) ?? (($b = QueryHints::maxBudget($text)) ? (int) round($b * 100) : null);
                    $items = $q !== '' ? $this->search->forQueries([$q], $max, self::MAX_RESULTS, excludeUserId: $user?->id) : collect();
                    $out['items'] = $items;
                    if (! empty($a['near_me']) && ! Near::current()) {
                        $out['client'][] = 'need_location';
                    }
                    $fallbackReply = $items->isEmpty()
                        ? "I couldn't find “{$q}” in stock yet. Try other words?"
                        : 'Here '.($items->count() === 1 ? 'is one match' : 'are '.$items->count().' matches').' for “'.$q.'”'.(Near::current() ? ', nearest first.' : '.').' Say “add the first one” to put it in your cart.';
                    break;

                case 'gift':
                    $recipient = in_array($a['recipient'] ?? null, GiftIdeas::RECIPIENTS, true) ? $a['recipient'] : GiftIdeas::recipientFrom($text);
                    $occasion = is_string($a['occasion'] ?? null) ? mb_substr($a['occasion'], 0, 40) : GiftIdeas::occasionFrom($text);
                    $budget = self::minor($a['budget'] ?? null) ?? (($b = QueryHints::maxBudget($text)) ? (int) round($b * 100) : null);
                    // Rules only here: the voice call above already was the one model call for this turn.
                    $items = $this->search->forQueries($this->gifts->rules($occasion, $recipient), $budget, self::MAX_RESULTS, excludeUserId: $user?->id);
                    $out['items'] = $items;
                    $fallbackReply = $items->isEmpty() ? "I couldn't find gift ideas in stock for that yet." : 'Some gift ideas'.($budget ? ' within '.Present::money($budget) : '').'. Want me to add one?';
                    break;

                case 'wallet':
                    if (! $user) {
                        $out['client'][] = 'login';
                        $fallbackReply = 'Sign in so I can see your wallet balance.';
                        break;
                    }
                    $balance = app(WalletService::class)->for($user)->available();
                    if ($balance <= 0) {
                        $fallbackReply = 'Your wallet is empty right now. Top it up from Wallet and ask me again.';
                        $out['items'] = collect();
                        break;
                    }
                    $q = trim((string) ($a['query'] ?? ''));
                    $items = $q !== '' ? $this->search->forQueries([$q], $balance, self::MAX_RESULTS, excludeUserId: $user->id) : $this->search->underBudget($balance, self::MAX_RESULTS, $user->id);
                    $out['items'] = $items;
                    $fallbackReply = $items->isEmpty() ? 'Nothing in stock under your balance of '.Present::money($balance).' yet.' : 'These fit your balance of '.Present::money($balance).'.';
                    break;

                case 'add_to_cart':
                    [$reply, $added, $open, $client] = $this->addToCart($request, $last, $a, $text);
                    $fallbackReply = $reply;
                    $out['cart_added'] = $added;
                    $out['open'] = $open;
                    $out['client'] = array_merge($out['client'], $client);
                    $plan['reply'] = null; // the outcome decides what we say
                    break;

                case 'trip':
                    $city = Trip::city((string) ($a['city'] ?? ''));
                    if (! $city) {
                        $fallbackReply = "I don't know that city yet. Try a big town near it.";
                        break;
                    }
                    Trip::set($request, $city);
                    $out['client'][] = 'reload_near';
                    $fallbackReply = "Okay — I'll show what's in stock around {$city['city']} for your trip.";
                    break;

                case 'clear_trip':
                    Trip::clear($request);
                    $out['client'][] = 'reload_near';
                    $fallbackReply = 'Trip cleared. Back to what is near you.';
                    break;

                case 'photo':
                    $out['client'][] = 'open_photo';
                    $fallbackReply = 'Sure — snap or pick a photo and I will find look-alikes.';
                    break;
            }
        }

        if ($out['items'] instanceof Collection) {
            $this->remember($request, $out['items']);
            // Never let the model claim results we don't have.
            if ($out['items']->isEmpty()) {
                $plan['reply'] = null;
            }
            $out['items'] = $out['items']->map(fn ($p) => Present::productCard($p->loadMissing(['cover', 'store:'.Present::STORE_CARD_COLUMNS, 'category:id,name,slug'])))->values()->all();
        } else {
            $out['items'] = null; // keep the previous cards on screen
        }
        $out['reply'] = $plan['reply'] ?? $fallbackReply ?? 'Tell me what you are looking for — like “black heels near me under 20k”.';
        $out['client'] = array_values(array_unique($out['client']));

        return $out;
    }

    /** @return array{0: string, 1: ?array, 2: ?string, 3: list<string>} */
    private function addToCart(Request $request, Collection $last, array $a, string $text): array
    {
        if (! $request->user()) {
            return ['Sign in first and I will add it to your cart.', null, null, ['login']];
        }
        if ($last->isEmpty()) {
            return ['Search for something first, then tell me which one to add.', null, null, []];
        }
        $n = is_numeric($a['n'] ?? null) ? (int) $a['n'] : (QueryHints::ordinal($text) ?? 1);
        $product = $n === -1 ? $last->last() : $last->values()->get(max(1, $n) - 1);
        if (! $product) {
            return ["I only showed {$last->count()} — which one did you mean?", null, null, []];
        }
        $product->refresh();
        if (! empty($product->options)) {
            return ['That one needs a size or colour — opening it so you can choose.', null, route('products.show', $product), []];
        }
        try {
            $this->checkout->add($request->user(), $product, 1);
            $this->engagement->record($request, 'cart_add', $product);
        } catch (ValidationException $e) {
            return [collect($e->errors())->flatten()->first() ?? "Couldn't add that one.", null, null, []];
        }
        $product->loadMissing('cover');

        return ['Added “'.$product->title.'” to your cart.', ['id' => $product->id, 'title' => $product->title, 'image' => $product->cover ? Present::imageUrl($product->cover->path) : null], null, ['cart_updated']];
    }

    /** Offline intents — same action shapes as the model. */
    public function rules(string $text): array
    {
        $t = mb_strtolower($text);
        $actions = [];
        if (preg_match('/\b(add|put|buy|cart|take|get me)\b/u', $t) && QueryHints::ordinal($t) !== null) {
            $actions[] = ['type' => 'add_to_cart', 'n' => QueryHints::ordinal($t)];
        } elseif (preg_match('/\b(cancel|clear|end|stop|forget)\b.*\btrip\b/u', $t)) {
            $actions[] = ['type' => 'clear_trip'];
        } elseif (preg_match('/\b(?:going to|trip to|travel(?:l)?ing to|visiting|i\'?ll be in|i will be in|heading to)\s+([\pL \-]{2,40})/u', $t, $m)) {
            $actions[] = ['type' => 'trip', 'city' => trim(preg_split('/\b(on|next|this|tomorrow|for|and)\b/u', $m[1])[0])];
        } elseif (preg_match('/\b(photo|picture|camera|snap)\b/u', $t)) {
            $actions[] = ['type' => 'photo'];
        } elseif (preg_match('/\b(gift|present for|surprise)\b/u', $t)) {
            $actions[] = ['type' => 'gift'];
        } elseif (preg_match('/\b(my balance|my wallet|what can i (get|afford|buy))\b/u', $t)) {
            $actions[] = ['type' => 'wallet', 'query' => trim(preg_replace('/\b(with )?(my balance|my wallet|what can i (get|afford|buy))\b/u', '', QueryHints::keywords($t)))];
        } else {
            $actions[] = ['type' => 'search', 'query' => QueryHints::keywords($text), 'near_me' => QueryHints::wantsNearby($text)];
        }

        return ['reply' => null, 'actions' => $actions];
    }

    private function lastResults(Request $request): Collection
    {
        $ids = $request->hasSession() ? (array) $request->session()->get(self::SESSION_LAST, []) : [];
        if (! $ids) {
            return collect();
        }
        $products = Product::query()->published()->whereIn('products.id', $ids)->get()->keyBy('id');

        return collect($ids)->map(fn ($id) => $products[$id] ?? null)->filter()->values();
    }

    private function remember(Request $request, Collection $items): void
    {
        if ($request->hasSession()) {
            $request->session()->put(self::SESSION_LAST, $items->pluck('id')->take(self::MAX_RESULTS)->values()->all());
        }
    }

    private static function minor(mixed $naira): ?int
    {
        return is_numeric($naira) && (float) $naira > 0 && (float) $naira < 1e9 ? (int) round((float) $naira * 100) : null;
    }
}
