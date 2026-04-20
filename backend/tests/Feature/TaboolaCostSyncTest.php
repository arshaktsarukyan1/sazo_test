<?php

namespace Tests\Feature;

use App\Contracts\TrafficSourceCostAdapterInterface;
use App\Models\Campaign;
use App\Models\CostEntry;
use App\Models\CostSyncRun;
use App\Models\Lander;
use App\Models\Session;
use App\Models\TrafficSource;
use App\Models\Visit;
use App\Services\Cost\CostSpendDayRow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaboolaCostSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_distributes_daily_spend_and_is_idempotent(): void
    {
        $this->app->instance(TrafficSourceCostAdapterInterface::class, new class implements TrafficSourceCostAdapterInterface {
            public function getSourceKey(): string
            {
                return 'taboola';
            }

            public function fetchSpendByDay(string $fromDate, string $toDate, array $externalCampaignIds): array
            {
                return [
                    new CostSpendDayRow('99001', '2026-04-10', 'US', 100.00),
                ];
            }
        });

        $source = TrafficSource::query()->create(['name' => 'Taboola', 'slug' => 'taboola']);
        $landerA = Lander::query()->create(['name' => 'A', 'url' => 'https://a.test/l']);
        $landerB = Lander::query()->create(['name' => 'B', 'url' => 'https://b.test/l']);
        $campaign = Campaign::query()->create([
            'traffic_source_id' => $source->id,
            'external_traffic_campaign_id' => '99001',
            'name' => 'T Campaign',
            'slug' => 't-campaign',
            'status' => 'active',
            'destination_url' => 'https://out.test/x',
        ]);
        $campaign->landers()->sync([
            $landerA->id => ['weight_percent' => 50, 'is_active' => true],
            $landerB->id => ['weight_percent' => 50, 'is_active' => true],
        ]);

        $session = Session::query()->create([
            'session_uuid' => (string) Str::uuid(),
            'country_code' => 'US',
            'device_type' => 'desktop',
        ]);

        Visit::query()->create([
            'campaign_id' => $campaign->id,
            'session_id' => $session->id,
            'lander_id' => $landerA->id,
            'country_code' => 'US',
            'device_type' => 'desktop',
            'created_at' => '2026-04-10 10:05:00',
            'updated_at' => '2026-04-10 10:05:00',
        ]);
        Visit::query()->create([
            'campaign_id' => $campaign->id,
            'session_id' => $session->id,
            'lander_id' => $landerB->id,
            'country_code' => 'US',
            'device_type' => 'desktop',
            'created_at' => '2026-04-10 10:20:00',
            'updated_at' => '2026-04-10 10:20:00',
        ]);

        $this->artisan('tds:sync-taboola', [
            '--from' => '2026-04-10',
            '--to' => '2026-04-10',
        ])->assertSuccessful();

        $entries = CostEntry::query()->where('campaign_id', $campaign->id)->orderBy('bucket_start')->get();
        $this->assertCount(2, $entries);
        $this->assertEqualsWithDelta(100.0, (float) $entries->sum('amount'), 0.02);

        $this->artisan('tds:sync-taboola', [
            '--from' => '2026-04-10',
            '--to' => '2026-04-10',
        ])->assertSuccessful();

        $entries2 = CostEntry::query()->where('campaign_id', $campaign->id)->get();
        $this->assertCount(2, $entries2);
        $this->assertEqualsWithDelta(100.0, (float) $entries2->sum('amount'), 0.02);

        $this->assertGreaterThanOrEqual(2, CostSyncRun::query()->count());
    }

    public function test_cli_idempotency_key_skips_duplicate_sync_without_second_cost_sync_run(): void
    {
        $this->app->instance(TrafficSourceCostAdapterInterface::class, new class implements TrafficSourceCostAdapterInterface {
            public function getSourceKey(): string
            {
                return 'taboola';
            }

            public function fetchSpendByDay(string $fromDate, string $toDate, array $externalCampaignIds): array
            {
                return [];
            }
        });

        $opts = [
            '--from' => '2026-04-01',
            '--to' => '2026-04-01',
            '--idempotency-key' => 'idem-cli-'.Str::uuid()->toString(),
        ];

        $this->artisan('tds:sync-taboola', $opts)->assertSuccessful();
        $n = CostSyncRun::query()->count();

        $this->artisan('tds:sync-taboola', $opts)->assertSuccessful();
        $this->assertSame($n, CostSyncRun::query()->count());
    }

    public function test_ops_sync_runs_returns_recent_rows(): void
    {
        CostSyncRun::query()->create([
            'source' => 'taboola',
            'status' => CostSyncRun::STATUS_SUCCESS,
            'window_from' => now()->subDay(),
            'window_to' => now(),
            'rows_upserted' => 3,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $this->withHeaders(['Authorization' => 'Bearer test-internal-token'])
            ->getJson('/api/v1/ops/sync-runs')
            ->assertOk()
            ->assertJsonPath('data.0.rows_upserted', 3);
    }
}
