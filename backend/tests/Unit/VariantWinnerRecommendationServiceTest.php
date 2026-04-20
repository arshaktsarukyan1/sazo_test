<?php

namespace Tests\Unit;

use App\Services\Kpi\KpiComputationService;
use App\Services\Kpi\VariantWinnerRecommendationService;
use App\Services\Kpi\WinnerRecommendationResult;
use App\Services\Kpi\WinnerThresholds;
use Tests\TestCase;

class VariantWinnerRecommendationServiceTest extends TestCase
{
    public function test_low_sample_returns_insufficient_data(): void
    {
        $svc = new VariantWinnerRecommendationService(new KpiComputationService);
        $res = $svc->recommend([
            [
                'variant_key' => 'offer:1',
                'clicks' => 10,
                'conversions' => 2,
                'revenue' => 20,
                'cost' => 5,
            ],
            [
                'variant_key' => 'offer:2',
                'clicks' => 15,
                'conversions' => 1,
                'revenue' => 10,
                'cost' => 4,
            ],
        ], new WinnerThresholds(minClicks: 100, confidenceFloorPercent: 0.5));

        $this->assertSame(WinnerRecommendationResult::STATUS_INSUFFICIENT_DATA, $res->status);
        $this->assertNull($res->recommendedVariantKey);
    }

    public function test_high_sample_clear_winner_returns_recommended(): void
    {
        $svc = new VariantWinnerRecommendationService(new KpiComputationService);
        $res = $svc->recommend([
            [
                'variant_key' => 'offer:1',
                'clicks' => 500,
                'conversions' => 120,
                'revenue' => 6000,
                'cost' => 1000,
            ],
            [
                'variant_key' => 'offer:2',
                'clicks' => 500,
                'conversions' => 80,
                'revenue' => 4000,
                'cost' => 1000,
            ],
        ], new WinnerThresholds(minClicks: 100, confidenceFloorPercent: 0.1));

        $this->assertSame(WinnerRecommendationResult::STATUS_RECOMMENDED, $res->status);
        $this->assertSame('offer:1', $res->recommendedVariantKey);
    }

    public function test_no_clear_winner_when_profit_tie_band(): void
    {
        $svc = new VariantWinnerRecommendationService(new KpiComputationService);
        $res = $svc->recommend([
            [
                'variant_key' => 'offer:1',
                'clicks' => 200,
                'conversions' => 40,
                'revenue' => 1000,
                'cost' => 400,
            ],
            [
                'variant_key' => 'offer:2',
                'clicks' => 200,
                'conversions' => 40,
                'revenue' => 1005,
                'cost' => 400,
            ],
        ], new WinnerThresholds(
            minClicks: 100,
            confidenceFloorPercent: 0.1,
            profitTieRelativeEpsilon: 0.02,
        ));

        $this->assertSame(WinnerRecommendationResult::STATUS_NO_CLEAR_WINNER, $res->status);
        $this->assertNull($res->recommendedVariantKey);
    }
}
