<?php

namespace App\Contracts;

/**
 * AI listing assistant. Suggestions are proposals the seller reviews —
 * never auto-published as facts, never invent brands/specs/warranties.
 */
interface ListingAi
{
    public function enabled(): bool;

    /**
     * @param  array{description:string, category?:?string, condition?:?string, city?:?string}  $input
     * @return list<string> up to 3 title suggestions (may be empty)
     */
    public function suggestTitles(array $input): array;

    /**
     * Rewrite the seller's own notes into a clean buyer-facing description.
     * Must only restate facts the seller provided.
     *
     * @param  array{title?:?string, notes:string, category?:?string, condition?:?string, city?:?string}  $input
     */
    public function suggestDescription(array $input): ?string;
}
