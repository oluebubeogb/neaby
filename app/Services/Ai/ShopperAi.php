<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Shopper-side "word" tasks on the same OpenAI-compatible endpoint as the
 * listing assistant (LISTING_AI_* → RunPod in production). Separate class so the
 * vendor title/description button is untouched.
 *
 * Every method returns null on any problem (off, timeout, bad JSON) and callers
 * fall back to plain rules. The model only ever shapes queries / short replies;
 * products, prices and stock always come from our database.
 */
class ShopperAi
{
    public function __construct(private readonly array $ai = [], private readonly array $cfg = []) {}

    public static function make(): self
    {
        return new self((array) config('neaby.ai', []), (array) config('neaby.shopper_ai', []));
    }

    /** Cheap config check (no network) — safe for shared props. */
    public function enabled(): bool
    {
        return (bool) ($this->cfg['enabled'] ?? true)
            && ($this->ai['driver'] ?? 'fake') === 'openai'
            && filled($this->ai['api_key'] ?? null)
            && filled($this->ai['base_url'] ?? null);
    }

    public function vision(): bool
    {
        return (bool) ($this->ai['vision'] ?? false);
    }

    /**
     * Plain-language search → structured filters.
     *
     * @param  list<array{slug:string,name:string}>  $categories
     * @return array{terms:string, category:?string, min:?float, max:?float, condition:?string, near_me:bool, summary:?string}|null
     */
    public function parseQuery(string $q, array $categories): ?array
    {
        $system = 'You turn a Nigerian shopper\'s plain-language request into marketplace search filters. '
            .'Reply ONLY with JSON: {"terms": "2-5 short product keywords incl. colour/material", "category": "one slug from the list or null", '
            .'"min": number|null, "max": number|null, "condition": "new"|"used"|"refurbished"|null, "near_me": true|false, "summary": "max 8 words"}. '
            .'Prices are Naira; "20k" = 20000. Never invent brands the shopper did not say. Drop filler like "find me", "I need", "near me".';
        $json = $this->json($system, json_encode(['request' => mb_substr($q, 0, 200), 'categories' => $categories], JSON_UNESCAPED_UNICODE), 220);
        if (! $json) {
            return null;
        }
        $slugs = array_column($categories, 'slug');
        $terms = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($json['terms'] ?? ''))));

        return [
            'terms' => mb_substr($terms, 0, 100),
            'category' => in_array($json['category'] ?? null, $slugs, true) ? $json['category'] : null,
            'min' => self::money($json['min'] ?? null),
            'max' => self::money($json['max'] ?? null),
            'condition' => in_array($json['condition'] ?? null, ['new', 'used', 'refurbished'], true) ? $json['condition'] : null,
            'near_me' => (bool) ($json['near_me'] ?? false),
            'summary' => self::short($json['summary'] ?? null, 80),
        ];
    }

    /**
     * Grounded listing Q&A. $listing holds ONLY this listing + its shop.
     *
     * @param  list<string>  $imageUrls  absolute URLs, sent only when LISTING_AI_VISION=true
     */
    public function answer(array $listing, string $question, array $imageUrls = []): ?string
    {
        $system = 'You answer a shopper\'s question about ONE marketplace listing on neaby (Nigeria). '
            .'Use ONLY the listing JSON (and photos, if given). If the answer is not there, say you are not sure and suggest '
            .'they message the seller. Never invent specs, sizes, brands, warranty, delivery times or prices. '
            .'Max 3 short sentences, friendly, plain text. Reply with JSON: {"answer": "..."}.';
        $text = json_encode(['listing' => $listing, 'question' => mb_substr($question, 0, 300)], JSON_UNESCAPED_UNICODE);
        $content = $text;
        if ($imageUrls && $this->vision()) {
            $content = [['type' => 'text', 'text' => $text]];
            foreach (array_slice($imageUrls, 0, 3) as $u) {
                $content[] = ['type' => 'image_url', 'image_url' => ['url' => $u]];
            }
        }
        $json = $this->json($system, $content, 220);

        return self::short($json['answer'] ?? null, 600);
    }

    /**
     * Gift / budget plan: a few catalogue queries to run. Never products.
     *
     * @return array{queries: list<string>, note: ?string}|null
     */
    public function plan(array $brief): ?array
    {
        $system = 'You plan a short shopping search for a Nigerian marketplace. Given the brief, reply ONLY with JSON: '
            .'{"queries": ["2-4 word product search", ... up to 5], "note": "one friendly sentence, max 18 words"}. '
            .'Queries must be generic product types (e.g. "perfume", "leather wallet", "wireless earbuds"), no brands unless asked, '
            .'no prices. Never claim specific items are available.';
        $json = $this->json($system, json_encode($brief, JSON_UNESCAPED_UNICODE), 220);
        if (! $json) {
            return null;
        }
        $queries = collect((array) ($json['queries'] ?? []))
            ->map(fn ($q) => is_string($q) ? trim(preg_replace('/[^\pL\pN\s\-]/u', ' ', $q)) : '')
            ->map(fn ($q) => mb_substr(preg_replace('/\s+/', ' ', $q), 0, 60))
            ->filter()->unique()->take(5)->values()->all();

        return $queries ? ['queries' => $queries, 'note' => self::short($json['note'] ?? null, 160)] : null;
    }

    /**
     * Voice "shop with me": short spoken reply + whitelisted actions.
     *
     * @param  array  $context  {last: [{n, id, title, price}], signed_in, balance?, trip?}
     * @return array{reply: ?string, actions: list<array>}|null
     */
    public function instruct(string $transcript, array $context): ?array
    {
        $system = 'You are neaby\'s voice shopping helper for a Nigerian marketplace. The shopper speaks; you reply in ONE or TWO short '
            .'spoken sentences and choose actions. You can ONLY use these actions: '
            .'{"type":"search","query":"keywords","max":number|null,"near_me":bool} | '
            .'{"type":"add_to_cart","n":position from the last results (1 = first)} | '
            .'{"type":"gift","occasion":"...","recipient":"...","budget":number|null} | '
            .'{"type":"wallet","query":"keywords or empty"} (items under the shopper\'s balance) | '
            .'{"type":"trip","city":"Nigerian city"} | {"type":"clear_trip"} | {"type":"photo"} (shopper wants to search by photo). '
            .'Never name or describe specific products, prices or stock yourself — the app shows real results after your actions. '
            .'"the second one" refers to n=2 in last results. Naira: "20k" = 20000. '
            .'Reply ONLY with JSON: {"reply": "...", "actions": [ ... at most 2 ... ]}.';
        $json = $this->json($system, json_encode(['said' => mb_substr($transcript, 0, 400), 'context' => $context], JSON_UNESCAPED_UNICODE), 260);
        if (! $json) {
            return null;
        }

        return [
            'reply' => self::short($json['reply'] ?? null, 240),
            'actions' => array_values(array_filter(array_slice((array) ($json['actions'] ?? []), 0, 2), 'is_array')),
        ];
    }

    /** One JSON chat completion. Null on any failure. */
    private function json(string $system, string|array $user, int $maxTokens): ?array
    {
        if (! $this->enabled()) {
            return null;
        }
        try {
            $res = Http::timeout(max(3, (int) ($this->cfg['timeout'] ?? $this->ai['timeout'] ?? 12)))
                ->connectTimeout(5)
                ->withToken((string) $this->ai['api_key'])
                ->post(rtrim((string) $this->ai['base_url'], '/').'/chat/completions', [
                    'model' => ($this->cfg['model'] ?? null) ?: ($this->ai['model'] ?? null),
                    'temperature' => 0.2,
                    'max_tokens' => $maxTokens,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                ]);
            if (! $res->successful()) {
                Log::warning('ShopperAi HTTP '.$res->status());

                return null;
            }

            return self::decode((string) $res->json('choices.0.message.content'));
        } catch (Throwable $e) {
            Log::warning('ShopperAi failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /** Tolerate ```json fences / prose around the object (common with open models). */
    public static function decode(string $content): ?array
    {
        $content = trim($content);
        $j = json_decode($content, true);
        if (is_array($j)) {
            return $j;
        }
        if (preg_match('/\{.*\}/s', $content, $m)) {
            $j = json_decode($m[0], true);

            return is_array($j) ? $j : null;
        }

        return null;
    }

    private static function money(mixed $v): ?float
    {
        if (is_string($v)) {
            $v = str_ireplace(['₦', 'ngn', 'naira', ','], '', trim($v));
            if (preg_match('/^(\d+(?:\.\d+)?)\s*k$/i', $v, $m)) {
                $v = (float) $m[1] * 1000;
            }
        }

        return is_numeric($v) && (float) $v > 0 && (float) $v < 1e9 ? round((float) $v, 2) : null;
    }

    private static function short(mixed $v, int $max): ?string
    {
        if (! is_string($v)) {
            return null;
        }
        $v = trim(preg_replace('/\s+/', ' ', strip_tags($v)));

        return $v === '' ? null : Str::limit($v, $max, '…');
    }
}
