<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Click;
use App\Models\Conversion;
use App\Models\CostEntry;
use App\Models\Kpi15mAggregate;
use App\Models\KpiDailyAggregate;
use App\Models\Session;
use App\Models\TrafficSource;
use App\Models\Visit;
use App\Services\Kpi\KpiAggregationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class KpiAggregationTest extends TestCase
{
    use RefreshDatabase;

    public function test_15m_and_daily_aggregates_match_raw_event_sql(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-10 14:37:00', 'UTC'));

        $source = TrafficSource::query()->create([
            'name' => 'Src',
            'slug' => 'src-'.Str::uuid(),
            'is_active' => true,
        ]);

        $campaign = Campaign::query()->create([
            'traffic_source_id' => $source->id,
            'name' => 'Camp',
            'slug' => 'camp-'.Str::uuid(),
            'status' => 'active',
            'destination_url' => 'https://example.com/out',
        ]);

        $session = Session::query()->create([
            'session_uuid' => (string) Str::uuid(),
            'country_code' => 'US',
            'device_type' => 'desktop',
        ]);

        Visit::query()->create([
            'campaign_id' => $campaign->id,
            'session_id' => $session->id,
            'country_code' => 'US',
            'device_type' => 'desktop',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Visit::query()->create([
            'campaign_id' => $campaign->id,
            'session_id' => $session->id,
            'country_code' => 'US',
            'device_type' => 'desktop',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Click::query()->create([
            'click_uuid' => (string) Str::uuid(),
            'campaign_id' => $campaign->id,
            'session_id' => $session->id,
            'country_code' => 'US',
            'device_type' => 'desktop',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Conversion::query()->create([
            'campaign_id' => $campaign->id,
            'click_id' => null,
            'source' => 'manual',
            'amount' => 25.5,
            'country_code' => 'US',
            'device_type' => 'desktop',
            'converted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        CostEntry::query()->create([
            'campaign_id' => $campaign->id,
            'source' => 'taboola',
            'country_code' => 'US',
            'amount' => 10.0,
            'bucket_start' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $bucketExpr = match (DB::getDriverName()) {
            'pgsql' => "DATE_TRUNC('hour', created_at) + (FLOOR(EXTRACT(MINUTE FROM created_at) / 15) * INTERVAL '15 minute')",
            'mysql', 'mariadb' => 'FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(created_at) / 900) * 900)',
            'sqlite' => "datetime((cast(strftime('%s', created_at) as integer) / 900) * 900, 'unixepoch')",
            default => $this->fail('Unsupported driver'),
        };

        $bucketKey = match (DB::getDriverName()) {
            'pgsql' => DB::selectOne("
                SELECT ({$bucketExpr})::text as b FROM visits WHERE campaign_id = ? LIMIT 1
            ", [$campaign->id])->b,
            'mysql', 'mariadb', 'sqlite' => DB::selectOne("
                SELECT {$bucketExpr} as b FROM visits WHERE campaign_id = ? LIMIT 1
            ", [$campaign->id])->b,
            default => null,
        };

        $rawVisitsInBucket = (int) DB::table('visits')
            ->where('campaign_id', $campaign->id)
            ->whereRaw("({$bucketExpr}) = ?", [$bucketKey])
            ->count();

        $this->assertSame(2, $rawVisitsInBucket);

        app(KpiAggregationService::class)->recompute15MinuteRange(
            now()->subHour(),
            now()->addHour(),
        );

        $desktopRow = Kpi15mAggregate::query()
            ->where('campaign_id', $campaign->id)
            ->where('country_code', 'US')
            ->where('device_type', 'desktop')
            ->orderByDesc('bucket_start')
            ->first();

        $this->assertNotNull($desktopRow);
        $this->assertSame(2, (int) $desktopRow->visits);
        $this->assertSame(1, (int) $desktopRow->clicks);
        $this->assertSame(1, (int) $desktopRow->conversions);
        $this->assertEqualsWithDelta(25.5, (float) $desktopRow->revenue, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $desktopRow->cost, 0.001);

        $costRow = Kpi15mAggregate::query()
            ->where('campaign_id', $campaign->id)
            ->where('country_code', 'US')
            ->whereNull('device_type')
            ->orderByDesc('bucket_start')
            ->first();

        $this->assertNotNull($costRow);
        $this->assertEqualsWithDelta(10.0, (float) $costRow->cost, 0.001);

        $this->assertSame(
            2,
            (int) Kpi15mAggregate::query()->where('campaign_id', $campaign->id)->sum('visits')
        );
        $this->assertEqualsWithDelta(
            10.0,
            (float) Kpi15mAggregate::query()->where('campaign_id', $campaign->id)->sum('cost'),
            0.001
        );

        $daily = KpiDailyAggregate::query()
            ->where('campaign_id', $campaign->id)
            ->where('country_code', 'US')
            ->where('device_type', 'desktop')
            ->whereDate('bucket_date', '2026-04-10')
            ->first();

        $this->assertNotNull($daily);
        $this->assertSame(2, (int) $daily->visits);
        $this->assertSame(1, (int) $daily->clicks);

        Carbon::setTestNow();
    }
}
