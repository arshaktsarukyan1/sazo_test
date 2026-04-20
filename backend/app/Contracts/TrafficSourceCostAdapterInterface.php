<?php

namespace App\Contracts;

use App\Services\Cost\CostSpendDayRow;

interface TrafficSourceCostAdapterInterface
{
    /**
     * Stable key stored on {@see \App\Models\CostEntry::$source}.
     */
    public function getSourceKey(): string;

    /**
     * @param  list<string>  $externalCampaignIds  Native IDs on the traffic source (e.g. Taboola campaign id).
     * @return list<CostSpendDayRow>
     */
    public function fetchSpendByDay(string $fromDate, string $toDate, array $externalCampaignIds): array;
}
