<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Kpi15mAggregate;
use App\Models\TrafficSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportKpiFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function internalHeaders(): array
    {
        return ['Authorization' => 'Bearer test-internal-token'];
    }

    public function test_kpi_report_applies_date_country_device_and_traffic_source_filters_and_returns_deltas(): void
    {
        $srcA = TrafficSource::query()->create(['name' => 'Src A', 'slug' => 'src-a', 'is_active' => true]);
        $srcB = TrafficSource::query()->create(['name' => 'Src B', 'slug' => 'src-b', 'is_active' => true]);

        $campA = Campaign::query()->create([
            'traffic_source_id' => $srcA->id,
            'name' => 'Camp A',
            'slug' => 'camp-a-'.Str::uuid(),
            'status' => 'active',
            'destination_url' => 'https://example.com/out',
        ]);
        $campB = Campaign::query()->create([
            'traffic_source_id' => $srcB->id,
            'name' => 'Camp B',
            'slug' => 'camp-b-'.Str::uuid(),
            'status' => 'active',
            'destination_url' => 'https://example.com/out',
        ]);

        // Window: 2026-04-10..2026-04-11 (2 days). Previous window: 2026-04-08..2026-04-09.
        $from = '2026-04-10';
        $to = '2026-04-11';

        // Previous window row (filtered in): visits 10, clicks 2, conv 1, rev 5, cost 1.
        Kpi15mAggregate::query()->create([
            'campaign_id' => $campA->id,
            'country_code' => 'US',
            'device_type' => 'desktop',
            'bucket_start' => '2026-04-08 12:00:00',
            'visits' => 10,
            'clicks' => 2,
            'conversions' => 1,
            'revenue' => 5,
            'cost' => 1,
        ]);

        // Current window row (filtered in): visits 20, clicks 6, conv 2, rev 9, cost 2.
        Kpi15mAggregate::query()->create([
            'campaign_id' => $campA->id,
            'country_code' => 'US',
            'device_type' => 'desktop',
            'bucket_start' => '2026-04-10 12:00:00',
            'visits' => 20,
            'clicks' => 6,
            'conversions' => 2,
            'revenue' => 9,
            'cost' => 2,
        ]);

        // Noise: different country.
        Kpi15mAggregate::query()->create([
            'campaign_id' => $campA->id,
            'country_code' => 'CA',
            'device_type' => 'desktop',
            'bucket_start' => '2026-04-10 12:00:00',
            'visits' => 999,
            'clicks' => 999,
            'conversions' => 999,
            'revenue' => 999,
            'cost' => 999,
        ]);

        // Noise: different traffic source.
        Kpi15mAggregate::query()->create([
            'campaign_id' => $campB->id,
            'country_code' => 'US',
            'device_type' => 'desktop',
            'bucket_start' => '2026-04-10 12:00:00',
            'visits' => 888,
            'clicks' => 888,
            'conversions' => 888,
            'revenue' => 888,
            'cost' => 888,
        ]);

        $res = $this->withHeaders($this->internalHeaders())->getJson(
            '/api/v1/reports/kpi?from='.$from
                .'&to='.$to
                .'&country_code=US'
                .'&device_type=desktop'
                .'&traffic_source_id='.$srcA->id
        );

        $res->assertOk();

        $current = $res->json('data.current');
        $previous = $res->json('data.previous');
        $delta = $res->json('data.delta');

        $this->assertSame(20, $current['visits']);
        $this->assertSame(6, $current['clicks']);
        $this->assertSame(2, $current['conversions']);
        $this->assertEqualsWithDelta(9.0, (float) $current['revenue'], 0.001);
        $this->assertEqualsWithDelta(2.0, (float) $current['cost'], 0.001);

        $this->assertSame(10, $previous['visits']);
        $this->assertSame(2, $previous['clicks']);
        $this->assertSame(1, $previous['conversions']);

        // KPI deltas should reflect current - previous for base metrics.
        $this->assertEqualsWithDelta(10.0, (float) ($delta['visits']['abs'] ?? 0), 0.001);
        $this->assertEqualsWithDelta(4.0, (float) ($delta['clicks']['abs'] ?? 0), 0.001);
        $this->assertEqualsWithDelta(1.0, (float) ($delta['conversions']['abs'] ?? 0), 0.001);
        $this->assertEqualsWithDelta(4.0, (float) ($delta['revenue']['abs'] ?? 0), 0.001);
        $this->assertEqualsWithDelta(1.0, (float) ($delta['cost']['abs'] ?? 0), 0.001);
    }
}

