<?php

namespace App\Services\Kpi;

/**
 * Scores variants using configurable floors. Returns a label only (no traffic changes).
 */
final class VariantWinnerRecommendationService
{
    public function __construct(
        private readonly KpiComputationService $kpiComputation,
    ) {}

    /**
     * @param list<array{
     *   variant_key: string,
     *   visits?: int,
     *   clicks: int,
     *   conversions: int,
     *   revenue: float|int|string,
     *   cost: float|int|string
     * }> $variants
     */
    public function recommend(array $variants, WinnerThresholds $thresholds): WinnerRecommendationResult
    {
        if ($variants === []) {
            return new WinnerRecommendationResult(
                WinnerRecommendationResult::STATUS_INSUFFICIENT_DATA,
                'No variants to compare.',
                null,
                [],
            );
        }

        $debug = [];
        $eligible = [];

        foreach ($variants as $row) {
            $clicks = (int) ($row['clicks'] ?? 0);
            $conversions = (int) ($row['conversions'] ?? 0);
            $revenue = (float) ($row['revenue'] ?? 0);
            $cost = (float) ($row['cost'] ?? 0);
            $visits = (int) ($row['visits'] ?? 0);
            $key = (string) $row['variant_key'];

            $totals = $this->kpiComputation->compute($visits, $clicks, $conversions, $revenue, $cost);
            $wilsonLb = $this->wilsonLowerBoundProportion($conversions, $clicks, $thresholds->wilsonZ);
            $wilsonCrLbPercent = $wilsonLb === null ? null : round($wilsonLb * 100, 2);

            $isEligible = $clicks >= $thresholds->minClicks
                && $wilsonCrLbPercent !== null
                && $wilsonCrLbPercent >= $thresholds->confidenceFloorPercent;

            $debug[] = [
                'variant_key' => $key,
                'visits' => $visits,
                'clicks' => $clicks,
                'conversions' => $conversions,
                'revenue' => $totals->revenue,
                'cost' => $totals->cost,
                'profit' => $totals->profit,
                'wilson_cr_lb_percent' => $wilsonCrLbPercent,
                'eligible' => $isEligible,
            ];

            if ($isEligible) {
                $eligible[] = ['key' => $key, 'profit' => $totals->profit];
            }
        }

        $maxClicks = max(array_map(static fn (array $v): int => (int) ($v['clicks'] ?? 0), $variants));

        if ($maxClicks < $thresholds->minClicks) {
            return new WinnerRecommendationResult(
                WinnerRecommendationResult::STATUS_INSUFFICIENT_DATA,
                'Insufficient data: minimum click threshold not met for any variant.',
                null,
                $debug,
            );
        }

        if ($eligible === []) {
            return new WinnerRecommendationResult(
                WinnerRecommendationResult::STATUS_NO_CLEAR_WINNER,
                'No variant meets the confidence floor at the current sample sizes.',
                null,
                $debug,
            );
        }

        usort($eligible, static fn (array $a, array $b): int => $b['profit'] <=> $a['profit']);
        $best = $eligible[0];
        $second = $eligible[1] ?? null;

        if ($second !== null && $this->isProfitTie($best['profit'], $second['profit'], $thresholds->profitTieRelativeEpsilon)) {
            return new WinnerRecommendationResult(
                WinnerRecommendationResult::STATUS_NO_CLEAR_WINNER,
                'Top variants are within the profit tie band.',
                null,
                $debug,
            );
        }

        return new WinnerRecommendationResult(
            WinnerRecommendationResult::STATUS_RECOMMENDED,
            'Recommended leader by profit among variants that pass minimum evidence thresholds.',
            $best['key'],
            $debug,
        );
    }

    private function isProfitTie(float $a, float $b, float $epsilon): bool
    {
        if ($epsilon <= 0.0) {
            return false;
        }

        $maxMag = max(abs($a), abs($b), 1e-9);

        return abs($a - $b) / $maxMag <= $epsilon;
    }

    /**
     * Wilson score interval lower bound for a binomial proportion (0–1), or null if trials = 0.
     */
    private function wilsonLowerBoundProportion(int $successes, int $trials, float $z): ?float
    {
        if ($trials === 0) {
            return null;
        }

        $p = $successes / $trials;
        $z2 = $z * $z;
        $denom = 1 + $z2 / $trials;
        $centre = ($p + $z2 / (2 * $trials)) / $denom;
        $radicand = ($p * (1 - $p) + $z2 / (4 * $trials)) / $trials;

        if ($radicand < 0) {
            $radicand = 0.0;
        }

        $margin = $z / $denom * sqrt($radicand);

        return max(0.0, $centre - $margin);
    }
}
