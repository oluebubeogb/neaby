<?php

namespace App\Services\Ai;

use App\Contracts\ListingAi;
use Illuminate\Support\Str;

/**
 * Deterministic, offline "AI": distils a title from the seller's own words.
 * Uses only what the seller wrote (plus chosen category/condition/city), so it
 * can never invent brands or specs. Default driver — needs no API key.
 */
class FakeListingAi implements ListingAi
{
    private const FILLER = [
        '/\b(hello|hi|hey)\b[,!.]*/i',
        '/\b(i am|i\'m|we are|we\'re)\s+(selling|offering)\b/i',
        '/\b(selling|for sale|now available|available now|available|in stock|dm (me|us)( for (price|more))?|call (me|us)|whatsapp (me|us)?)\b[:,!.]*/i',
        '/\b(please|kindly|pls)\b/i',
        '/\b(very|super|really)\s+(neat|clean|good)\b/i',
    ];

    /** Chatty bits removed from descriptions (facts are kept as-is). */
    private const DESC_FILLER = [
        '/^\s*(hello|hi|hey)( (guys|everyone|all))?\b[,!.]*/i',
        '/\b(dm (me|us)( for (price|more))?|call (me|us)|whatsapp (me|us))\b[:,!.]*/i',
        '/\b(please|kindly|pls)\b/i',
    ];

    /** Brand / product spellings sellers often type in lowercase. */
    private const CANON = [
        'iphone' => 'iPhone', 'ipad' => 'iPad', 'macbook' => 'MacBook', 'airpods' => 'AirPods', 'imac' => 'iMac',
        'ps4' => 'PS4', 'ps5' => 'PS5', 'xbox' => 'Xbox', 'hp' => 'HP', 'lg' => 'LG', 'tv' => 'TV', 'led' => 'LED',
        'usb' => 'USB', 'jbl' => 'JBL', 'tecno' => 'Tecno', 'itel' => 'itel', 'oraimo' => 'Oraimo', 'ac' => 'AC',
    ];

    public function enabled(): bool
    {
        return true;
    }

    public function suggestTitles(array $input): array
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($input['description'] ?? ''))));
        if (mb_strlen($text) < 4) {
            return [];
        }

        // First clause usually names the item.
        $first = preg_split('/(?<=[.!?\n])\s+|\s+[-–—|]\s+|;\s*/u', $text)[0] ?? $text;
        foreach (self::FILLER as $re) {
            $first = preg_replace($re, ' ', $first);
        }
        $first = trim(preg_replace('/\s+/', ' ', $first), " \t\n\r\0\x0B.,!:-");
        $first = preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', '', $first); // emoji
        $first = trim($first);
        if ($first === '') {
            return [];
        }

        $core = Str::limit($this->titleCase($first), 64, '');
        $condition = match ($input['condition'] ?? null) {
            'used' => 'Fairly Used',
            'refurbished' => 'Refurbished',
            default => null,
        };

        $suggestions = [$core];
        if ($condition && ! Str::contains(Str::lower($core), ['used', 'refurb', 'new'])) {
            $suggestions[] = "{$core} ({$condition})";
        }
        if (! empty($input['city'])) {
            $suggestions[] = "{$core} – {$input['city']}";
        }
        if (! empty($input['category']) && ! Str::contains(Str::lower($core), Str::lower(Str::before($input['category'], ' ')))) {
            $suggestions[] = "{$core} | {$input['category']}";
        }

        return array_values(array_slice(array_unique(array_map(fn ($s) => Str::limit($s, 90, ''), $suggestions)), 0, 3));
    }

    /**
     * Offline description writer: tidies the seller's notes into short
     * paragraphs + a "Details" list built only from fields they chose.
     */
    public function suggestDescription(array $input): ?string
    {
        $notes = trim(preg_replace('/[ \t]+/', ' ', strip_tags((string) ($input['notes'] ?? ''))));
        if (mb_strlen($notes) < 4) {
            return null;
        }
        $notes = preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', '', $notes);
        $sentences = preg_split('/(?<=[.!?])\s+|\n+/u', $notes) ?: [];
        $clean = [];
        foreach ($sentences as $s) {
            foreach (self::DESC_FILLER as $re) {
                $s = preg_replace($re, ' ', $s);
            }
            $s = preg_replace_callback('/\b[\w]+\b/u', fn ($m) => self::CANON[Str::lower($m[0])] ?? $m[0], $s);
            $s = preg_replace('/\s+([,.!?])/', '$1', preg_replace('/\s+/', ' ', $s));
            $s = trim($s, " \t,;-");
            if ($s === '' || mb_strlen($s) < 3) {
                continue;
            }
            $s = Str::ucfirst($s);
            if (! preg_match('/[.!?]$/u', $s)) {
                $s .= '.';
            }
            $clean[] = $s;
        }
        if (! $clean) {
            return null;
        }

        $title = trim((string) ($input['title'] ?? ''));
        $intro = $title !== '' ? $title.'.' : null;
        $body = implode(' ', $clean);

        $details = array_filter([
            ! empty($input['condition']) ? 'Condition: '.match ($input['condition']) {
                'used' => 'Fairly used', 'refurbished' => 'Refurbished', default => 'Brand new',
            } : null,
            ! empty($input['category']) ? 'Category: '.$input['category'] : null,
            ! empty($input['city']) ? 'Location: '.$input['city'] : null,
        ]);

        $out = trim(($intro && ! Str::startsWith(Str::lower($body), Str::lower($title)) ? $intro.' ' : '').$body);
        if ($details) {
            $out .= "\n\nDetails\n• ".implode("\n• ", $details);
        }
        $out .= "\n\nMessage the seller to confirm availability before paying.";

        return Str::limit($out, 4800, '');
    }

    private function titleCase(string $s): string
    {
        $small = ['a', 'an', 'and', 'for', 'in', 'of', 'on', 'or', 'the', 'to', 'with'];
        $words = explode(' ', $s);
        foreach ($words as $i => $w) {
            if ($w === '') {
                continue;
            }
            $lw = mb_strtolower($w);
            if (isset(self::CANON[$lw])) {
                $words[$i] = self::CANON[$lw];

                continue;
            }
            // Storage/size units: 256gb → 256GB, 1tb → 1TB.
            if (preg_match('/^(\d+)(gb|tb|mb|mah|w)$/i', $w, $m)) {
                $words[$i] = $m[1].(strtolower($m[2]) === 'mah' ? 'mAh' : strtoupper($m[2]));

                continue;
            }
            // Keep model-ish tokens (iPhone, 128GB, PS5) as written.
            if (preg_match('/[A-Z].*[A-Z]|\d|^[a-z]+[A-Z]/', $w)) {
                continue;
            }
            $words[$i] = ($i > 0 && in_array($lw, $small, true)) ? $lw : Str::ucfirst($lw);
        }

        return implode(' ', $words);
    }
}
