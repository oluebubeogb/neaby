<?php

namespace App\Services\Search;

/**
 * Turns a free-text query into search tokens: lower-cased words, stop-words
 * dropped, simple English plurals folded ("hairs" → "hair", "dresses" → "dress",
 * "watches" → "watch", "batteries" → "battery"). Tokens are letters/digits only,
 * so they are safe to splice into a Postgres tsquery.
 */
class SearchTerms
{
    public const MAX_TOKENS = 8;

    public const MAX_TOKEN_LENGTH = 40;

    private const STOP = ['a', 'an', 'and', 'or', 'the', 'for', 'of', 'in', 'on', 'with', 'to', 'at', 'by', 'from', 'is', 'my', 'me', 'i'];

    /** @return list<string> raw (lower-cased, unstemmed) tokens, de-duplicated by stem */
    public static function tokens(string $q): array
    {
        $q = mb_strtolower(mb_substr(trim($q), 0, 100));
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $out = [];
        foreach ($parts as $p) {
            $p = mb_substr($p, 0, self::MAX_TOKEN_LENGTH);
            if (in_array($p, self::STOP, true) || (mb_strlen($p) < 2 && ! ctype_digit($p))) {
                continue;
            }
            $out[self::stem($p)] ??= $p;
            if (count($out) >= self::MAX_TOKENS) {
                break;
            }
        }

        return array_values($out);
    }

    /** @return list<string> stemmed tokens (what ILIKE / LIKE matches on) */
    public static function stems(string $q): array
    {
        return array_map(self::stem(...), self::tokens($q));
    }

    /** Minimal plural folding — good enough for catalogue words, never shorter than 3 chars. */
    public static function stem(string $w): string
    {
        $len = mb_strlen($w);
        if ($len <= 3 || ctype_digit($w)) {
            return $w;
        }
        if (str_ends_with($w, 'ies') && $len > 4) {
            return mb_substr($w, 0, -3).'y';            // batteries → battery
        }
        if (str_ends_with($w, 'sses')) {
            return mb_substr($w, 0, -2);                // dresses → dress
        }
        if (preg_match('/(x|z|ch|sh)es$/u', $w)) {
            return mb_substr($w, 0, -2);                // watches → watch, boxes → box
        }
        if (str_ends_with($w, 's') && ! preg_match('/(ss|us|is)$/u', $w)) {
            return mb_substr($w, 0, -1);                // hairs → hair, shoes → shoe
        }

        return $w;
    }

    /** Postgres prefix tsquery for ONE token, e.g. "hairs" → "hairs:*" (english config stems it). */
    public static function tsToken(string $token): string
    {
        return preg_replace('/[^\p{L}\p{N}]/u', '', $token).':*';
    }

    /** OR tsquery across all tokens: "hairs:* | shoes:*". */
    public static function tsAny(array $tokens): string
    {
        return implode(' | ', array_map(self::tsToken(...), $tokens));
    }

    public static function like(string $stem): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $stem).'%';
    }
}
