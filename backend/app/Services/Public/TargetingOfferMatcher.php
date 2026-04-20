<?php

namespace App\Services\Public;

/**
 * Picks the first targeting rule that matches session geo/device.
 * Rules must already be ordered (e.g. geo+device before geo-only), as produced by ClickController queries.
 */
final class TargetingOfferMatcher
{
    /**
     * @param  list<array{country_code: ?string, device_type: ?string, priority: int, offer_id: int, offer_url: string}>  $rulesOrdered
     * @return array{id: int, url: string, weight_percent: int, is_active: bool}|null
     */
    public function firstMatchingOffer(array $rulesOrdered, ?string $sessionCountry, ?string $sessionDevice): ?array
    {
        foreach ($rulesOrdered as $rule) {
            $countryMatches = $rule['country_code'] === null || $rule['country_code'] === $sessionCountry;
            $deviceMatches = $rule['device_type'] === null || $rule['device_type'] === $sessionDevice;

            if ($countryMatches && $deviceMatches) {
                return [
                    'id' => $rule['offer_id'],
                    'url' => $rule['offer_url'],
                    'weight_percent' => 100,
                    'is_active' => true,
                ];
            }
        }

        return null;
    }
}
