<?php

namespace Tests\Unit;

use App\Services\Public\TargetingOfferMatcher;
use PHPUnit\Framework\TestCase;

final class TargetingOfferMatcherTest extends TestCase
{
    public function test_geo_and_device_rule_wins_over_broader_rules_first_in_list_order(): void
    {
        $matcher = new TargetingOfferMatcher;
        $rules = [
            ['country_code' => 'FR', 'device_type' => 'mobile', 'priority' => 1, 'offer_id' => 1, 'offer_url' => 'https://a'],
            ['country_code' => 'FR', 'device_type' => null, 'priority' => 2, 'offer_id' => 2, 'offer_url' => 'https://b'],
            ['country_code' => null, 'device_type' => null, 'priority' => 99, 'offer_id' => 3, 'offer_url' => 'https://c'],
        ];

        $pick = $matcher->firstMatchingOffer($rules, 'FR', 'mobile');
        $this->assertSame(1, $pick['id']);
        $this->assertSame('https://a', $pick['url']);
    }

    public function test_falls_back_to_geo_only_when_device_differs(): void
    {
        $matcher = new TargetingOfferMatcher;
        $rules = [
            ['country_code' => 'FR', 'device_type' => 'mobile', 'priority' => 1, 'offer_id' => 1, 'offer_url' => 'https://a'],
            ['country_code' => 'FR', 'device_type' => null, 'priority' => 2, 'offer_id' => 2, 'offer_url' => 'https://b'],
        ];

        $pick = $matcher->firstMatchingOffer($rules, 'FR', 'desktop');
        $this->assertSame(2, $pick['id']);
    }

    public function test_returns_null_when_no_rule_matches(): void
    {
        $matcher = new TargetingOfferMatcher;
        $rules = [
            ['country_code' => 'DE', 'device_type' => null, 'priority' => 1, 'offer_id' => 9, 'offer_url' => 'https://x'],
        ];

        $this->assertNull($matcher->firstMatchingOffer($rules, 'US', 'desktop'));
    }
}
