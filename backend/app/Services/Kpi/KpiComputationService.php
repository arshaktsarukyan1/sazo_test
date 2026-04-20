<?php

namespace App\Services\Kpi;

/**
 * Pure KPI math for dashboard aggregates.
 *
 * Formulas (exact):
 * - CTR = clicks / visits * 100
 * - CR = conversions / clicks * 100
 * - Profit = revenue - cost
 * - ROI = (revenue - cost) / cost * 100
 * - CPA = cost / conversions
 * - EPC = revenue / clicks
 */
final class KpiComputationService
{
    private const DECIMALS = 2;

    public function compute(
        int $visits,
        int $clicks,
        int $conversions,
        string|int|float $revenue,
        string|int|float $cost,
    ): KpiTotals {
        $revenue = $this->toFloat($revenue);
        $cost = $this->toFloat($cost);
        $profit = $revenue - $cost;

        $ctr = $visits > 0 ? round(($clicks / $visits) * 100, self::DECIMALS) : null;
        $cr = $clicks > 0 ? round(($conversions / $clicks) * 100, self::DECIMALS) : null;
        $roi = $cost > 0 ? round((($revenue - $cost) / $cost) * 100, self::DECIMALS) : null;
        $cpa = $conversions > 0 ? round($cost / $conversions, self::DECIMALS) : null;
        $epc = $clicks > 0 ? round($revenue / $clicks, self::DECIMALS) : null;

        return new KpiTotals(
            visits: $visits,
            clicks: $clicks,
            conversions: $conversions,
            revenue: round($revenue, self::DECIMALS),
            cost: round($cost, self::DECIMALS),
            profit: round($profit, self::DECIMALS),
            ctr: $ctr,
            cr: $cr,
            roi: $roi,
            cpa: $cpa,
            epc: $epc,
        );
    }

    private function toFloat(string|int|float $v): float
    {
        return (float) $v;
    }
}
