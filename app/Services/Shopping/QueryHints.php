<?php

namespace App\Services\Shopping;

/**
 * Cheap, offline reading of what a shopper typed or said. Decides when a
 * search is worth one AI parse (plain-language) and powers the no-AI fallbacks.
 */
class QueryHints
{
    private const NATURAL = '/\b(near me|close to me|around me|nearby|under|below|less than|cheaper than|not more than|max(imum)?|budget|for my|for a|for her|for him|gift|looking for|i need|i want|find me|show me|something|cheap|affordable|that is|which is|to wear|good for)\b|₦|\d+\s?k\b/iu';

    public static function looksNatural(string $q): bool
    {
        $q = trim($q);
        if ($q === '' || mb_strlen($q) < 6) {
            return false;
        }
        $words = preg_split('/\s+/u', $q) ?: [];

        return count($words) >= 4 || (bool) preg_match(self::NATURAL, $q);
    }

    /** "under 20k", "below ₦15,000", "max 5000" → 20000.0 (Naira) */
    public static function maxBudget(string $q): ?float
    {
        if (preg_match('/\b(?:under|below|less than|cheaper than|not more than|max(?:imum)?|within|budget(?: of| is)?)\s*(?:₦|ngn|n)?\s*([\d][\d,\.]*)\s*(k|m)?\b/iu', $q, $m)) {
            return self::amount($m[1], $m[2] ?? '');
        }

        return null;
    }

    public static function amount(string $n, string $suffix = ''): ?float
    {
        $v = (float) str_replace(',', '', $n);
        $v *= match (strtolower($suffix)) { 'k' => 1000, 'm' => 1_000_000, default => 1 };

        return $v > 0 && $v < 1e9 ? $v : null;
    }

    public static function wantsNearby(string $q): bool
    {
        return (bool) preg_match('/\b(near me|close to me|around me|nearby|close by)\b/iu', $q);
    }

    /** Strip shopper filler + budget/nearby phrases → product keywords. */
    public static function keywords(string $q): string
    {
        $q = preg_replace('/\b(?:under|below|less than|cheaper than|not more than|max(?:imum)?|within|budget(?: of| is)?)\s*(?:₦|ngn|n)?\s*[\d][\d,\.]*\s*(?:k|m)?\b/iu', ' ', $q);
        $q = preg_replace('/\b(near me|close to me|around me|nearby|close by|please|pls|kindly|i need|i want|i\'m looking for|looking for|find me|show me|get me|can you|could you|search for|search|some|something|a|an|the|for me)\b/iu', ' ', $q);

        return trim(preg_replace('/\s+/u', ' ', preg_replace('/[^\pL\pN\s\-]/u', ' ', $q)));
    }

    /** "the second one", "number 3", "first" → 2 / 3 / 1 */
    public static function ordinal(string $q): ?int
    {
        $words = ['first' => 1, '1st' => 1, 'one' => 1, 'second' => 2, '2nd' => 2, 'two' => 2, 'third' => 3, '3rd' => 3, 'three' => 3,
            'fourth' => 4, '4th' => 4, 'four' => 4, 'fifth' => 5, '5th' => 5, 'five' => 5, 'sixth' => 6, '6th' => 6, 'six' => 6, 'last' => -1];
        if (preg_match('/\b(?:number|no\.?|item|#)\s*(\d{1,2})\b/iu', $q, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/\b('.implode('|', array_map('preg_quote', array_keys($words))).')\b(?:\s+one)?/iu', $q, $m)) {
            return $words[strtolower($m[1])];
        }

        return null;
    }
}
