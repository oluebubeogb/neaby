<?php

namespace App\Services\Ai;

use App\Contracts\ListingAi;

class NullListingAi implements ListingAi
{
    public function enabled(): bool
    {
        return false;
    }

    public function suggestTitles(array $input): array
    {
        return [];
    }

    public function suggestDescription(array $input): ?string
    {
        return null;
    }
}
