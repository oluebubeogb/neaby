<?php

namespace Tests\Unit;

use App\Services\Geo\Proximity;
use App\Services\Search\CatalogueSearch;
use App\Services\Search\SearchTerms;
use Tests\TestCase;

class SearchTermsTest extends TestCase
{
    public function test_multi_word_queries_are_tokenized_and_plurals_folded(): void
    {
        $this->assertSame(['hairs', 'shoes'], SearchTerms::tokens('  Hairs   shoes '));
        $this->assertSame(['hair', 'shoe'], SearchTerms::stems('hairs shoes'));
        $this->assertSame(['dress', 'watch', 'battery', 'box', 'glass'], SearchTerms::stems('dresses, watches & batteries boxes glass'));
        $this->assertSame(['phone'], SearchTerms::stems('the phones for'), 'stop-words dropped, duplicates collapse');
        $this->assertSame('batter', CatalogueSearch::likeRoot('battery'));
    }

    public function test_tokens_are_safe_for_tsquery_and_capped(): void
    {
        $this->assertSame("hairs:* | shoes:*", SearchTerms::tsAny(SearchTerms::tokens("hairs' | shoes:*!")));
        $this->assertCount(SearchTerms::MAX_TOKENS, SearchTerms::tokens(implode(' ', range('a', 'z')).' '.implode(' ', array_map(fn ($i) => "word{$i}", range(1, 20)))));
    }

    public function test_travel_labels_are_human_and_honest(): void
    {
        $near = new \App\Services\Geo\Near(5.5250, 7.4922);
        $store = new \App\Models\Store;
        $store->forceFill(['lat' => 5.5280, 'lng' => 7.4930, 'city' => 'Umuahia', 'location_confirmed_at' => now()]);
        $this->assertMatchesRegularExpression('/^\d+ min walk$/', Proximity::label($near, $store));

        $store->forceFill(['lat' => 5.5600, 'lng' => 7.5200]);
        $this->assertMatchesRegularExpression('/^\d+ min drive$/', Proximity::label($near, $store));

        $store->forceFill(['lat' => 6.1413, 'lng' => 6.8029, 'city' => 'Onitsha']); // ~100 km
        $this->assertMatchesRegularExpression('/^About [\d.]+ hr away$/', Proximity::label($near, $store));

        $store->forceFill(['lat' => 9.0765, 'lng' => 7.3986, 'city' => 'Abuja']);
        $this->assertSame('In Abuja', Proximity::label($near, $store));
    }
}
