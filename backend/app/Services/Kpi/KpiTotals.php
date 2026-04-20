<?php

namespace App\Services\Kpi;

/**
 * KPI snapshot: counts, money, and derived rates.
 *
 * Rounding: {@see KpiComputationService} applies PHP round() HALF_UP to 2 decimal places
 * for revenue, cost, profit, CTR, CR, ROI, CPA, and EPC. Undefined rates are null (zero-division).
 */
final readonly class KpiTotals
{
    public function __construct(
        public int $visits,
        public int $clicks,
        public int $conversions,
        public float $revenue,
        public float $cost,
        public float $profit,
        public ?float $ctr,
        public ?float $cr,
        public ?float $roi,
        public ?float $cpa,
        public ?float $epc,
    ) {}

    /**
     * @return array<string, int|float|null>
     */
    public function toArray(): array
    {
        return [
            'visits' => $this->visits,
            'clicks' => $this->clicks,
            'conversions' => $this->conversions,
            'revenue' => $this->revenue,
            'cost' => $this->cost,
            'profit' => $this->profit,
            'ctr' => $this->ctr,
            'cr' => $this->cr,
            'roi' => $this->roi,
            'cpa' => $this->cpa,
            'epc' => $this->epc,
        ];
    }
}
