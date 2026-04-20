<?php

namespace Tests\Unit;

use App\Services\Kpi\KpiComputationService;
use Tests\TestCase;

class KpiComputationServiceTest extends TestCase
{
    /**
     * @return iterable<string, array{
     *   visits:int,
     *   clicks:int,
     *   conversions:int,
     *   revenue:float,
     *   cost:float,
     *   expectCtr:?float,
     *   expectCr:?float,
     *   expectProfit:float,
     *   expectRoi:?float,
     *   expectCpa:?float,
     *   expectEpc:?float
     * }>
     */
    public static function happyPathProvider(): iterable
    {
        yield 'ctr_cr_roi_cpa_epc' => [[
            'visits' => 100,
            'clicks' => 25,
            'conversions' => 5,
            'revenue' => 500.0,
            'cost' => 200.0,
            'expectCtr' => 25.0,
            'expectCr' => 20.0,
            'expectProfit' => 300.0,
            'expectRoi' => 150.0,
            'expectCpa' => 40.0,
            'expectEpc' => 20.0,
        ]];

        yield 'rounding_two_decimals' => [[
            'visits' => 3,
            'clicks' => 1,
            'conversions' => 0,
            'revenue' => 10.0,
            'cost' => 3.0,
            'expectCtr' => 33.33,
            'expectCr' => 0.0,
            'expectProfit' => 7.0,
            'expectRoi' => 233.33,
            'expectCpa' => null,
            'expectEpc' => 10.0,
        ]];
    }

    /**
     * @dataProvider happyPathProvider
     */
    public function test_computes_rates_with_rounding_half_up_two_decimals(array $c): void
    {
        $svc = new KpiComputationService;
        $k = $svc->compute(
            $c['visits'],
            $c['clicks'],
            $c['conversions'],
            $c['revenue'],
            $c['cost'],
        );

        $this->assertSame($c['expectCtr'], $k->ctr);
        $this->assertSame($c['expectCr'], $k->cr);
        $this->assertEqualsWithDelta($c['expectProfit'], $k->profit, 0.001);
        $this->assertSame($c['expectRoi'], $k->roi);
        $this->assertSame($c['expectCpa'], $k->cpa);
        $this->assertSame($c['expectEpc'], $k->epc);
    }

    public function test_zero_division_yields_null_rates(): void
    {
        $svc = new KpiComputationService;
        $k = $svc->compute(0, 0, 0, 100.0, 0.0);

        $this->assertNull($k->ctr);
        $this->assertNull($k->cr);
        $this->assertNull($k->roi);
        $this->assertNull($k->cpa);
        $this->assertNull($k->epc);
        $this->assertSame(100.0, $k->revenue);
        $this->assertSame(0.0, $k->cost);
        $this->assertSame(100.0, $k->profit);
    }

    public function test_roi_null_when_cost_zero_even_with_revenue(): void
    {
        $k = (new KpiComputationService)->compute(10, 5, 1, 99.0, 0.0);

        $this->assertNull($k->roi);
        $this->assertSame(50.0, $k->ctr);
        $this->assertSame(20.0, $k->cr);
        $this->assertSame(19.8, $k->epc);
    }

    public function test_cpa_null_when_no_conversions(): void
    {
        $k = (new KpiComputationService)->compute(10, 5, 0, 0.0, 50.0);

        $this->assertNull($k->cpa);
        $this->assertSame(0.0, $k->cr);
    }

    public function test_epc_null_when_no_clicks_but_visits_positive(): void
    {
        $k = (new KpiComputationService)->compute(10, 0, 0, 100.0, 10.0);

        $this->assertNull($k->epc);
        $this->assertSame(0.0, $k->ctr);
    }
}
