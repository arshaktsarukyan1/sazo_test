<?php

namespace App\Services\Cost;

/**
 * Normalized daily spend row from a traffic-source cost adapter (e.g. Taboola campaign-day).
 */
final readonly class CostSpendDayRow
{
    public function __construct(
        public string $externalCampaignId,
        public string $day,
        public ?string $countryCode,
        public float $amount,
    ) {}
}
