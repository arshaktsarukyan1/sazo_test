<?php

namespace Tests\Feature;

use App\Contracts\TrafficSourceCostAdapterInterface;
use App\Models\Campaign;
use App\Models\Click;
use App\Models\Conversion;
use App\Models\Lander;
use App\Models\Offer;
use App\Models\Session;
use App\Models\TrafficSource;
use App\Models\Visit;
use App\Services\Cost\CostSpendDayRow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Backend "E2E" chain: internal APIs + public tracking + webhook + Taboola sync + KPI jobs + reports.
 */
class CriticalScenarioBackendE2ETest extends TestCase
{
    use RefreshDatabase;

    private function auth(): array
    {
        return ['Authorization' => 'Bearer test-internal-token'];
    }

    public function test_campaign_tracking_click_webhook_cost_sync_and_reports(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-02 14:30:00', 'UTC'));

        Config::set('tds.winner_recommendation.min_clicks', 8);
        Config::set('tds.winner_recommendation.confidence_floor_percent', 0.01);
        Config::set('tds.redirect_rate_limit_per_minute', 2000);
        Config::set('tds.click_rate_limit_per_minute', 2000);

        $this->app->instance(TrafficSourceCostAdapterInterface::class, new class implements TrafficSourceCostAdapterInterface {
            public function getSourceKey(): string
            {
                return 'taboola';
            }

            public function fetchSpendByDay(string $fromDate, string $toDate, array $externalCampaignIds): array
            {
                return [
                    new CostSpendDayRow('ext-e2e-42', '2026-05-02', 'US', 80.00),
                ];
            }
        });

        $ts = TrafficSource::query()->create([
            'name' => 'Taboola E2E',
            'slug' => 'taboola',
            'is_active' => true,
        ]);

        $l1 = $this->withHeaders($this->auth())->postJson('/api/v1/landers', [
            'name' => 'Lander A',
            'url' => 'https://e2e-lander-a.example/path',
        ])->assertCreated()->json('data.id');

        $l2 = $this->withHeaders($this->auth())->postJson('/api/v1/landers', [
            'name' => 'Lander B',
            'url' => 'https://e2e-lander-b.example/path',
        ])->assertCreated()->json('data.id');

        $o1 = $this->withHeaders($this->auth())->postJson('/api/v1/offers', [
            'name' => 'Offer A',
            'url' => 'https://e2e-offer-a.example/o',
        ])->assertCreated()->json('data.id');

        $o2 = $this->withHeaders($this->auth())->postJson('/api/v1/offers', [
            'name' => 'Offer B',
            'url' => 'https://e2e-offer-b.example/o',
        ])->assertCreated()->json('data.id');

        $slug = 'e2e-camp-'.Str::lower(Str::random(10));

        $camp = $this->withHeaders($this->auth())->postJson('/api/v1/campaigns', [
            'traffic_source_id' => $ts->id,
            'name' => 'E2E Campaign',
            'slug' => $slug,
            'status' => 'active',
            'destination_url' => 'https://e2e-fallback.example/out',
            'external_traffic_campaign_id' => 'ext-e2e-42',
            'landers' => [
                ['id' => $l1, 'weight_percent' => 50, 'is_active' => true],
                ['id' => $l2, 'weight_percent' => 50, 'is_active' => true],
            ],
            'offers' => [
                ['id' => $o1, 'weight_percent' => 50, 'is_active' => true],
                ['id' => $o2, 'weight_percent' => 50, 'is_active' => true],
            ],
        ])->assertCreated()->json('data');

        $campaignId = (int) $camp['id'];

        for ($i = 0; $i < 12; $i++) {
            $this->get('/api/campaign/'.$slug)->assertRedirect();
        }

        $sessionUs = Session::query()->create([
            'session_uuid' => 'e2e-us-desktop',
            'country_code' => 'US',
            'device_type' => 'desktop',
        ]);
        $sessionFr = Session::query()->create([
            'session_uuid' => 'e2e-fr-mobile',
            'country_code' => 'FR',
            'device_type' => 'mobile',
        ]);

        $this->get('/api/click?campaign='.$slug.'&sid=e2e-us-desktop')->assertRedirect();
        $this->get('/api/click?campaign='.$slug.'&sid=e2e-fr-mobile')->assertRedirect();

        $clickA = Click::query()->where('campaign_id', $campaignId)->where('offer_id', $o1)->first();
        $this->assertNotNull($clickA);

        Config::set('tds.shopify_webhook_secret', 'e2e-shopify-secret');
        $payload = [
            'id' => 900_001,
            'total_price' => '120.00',
            'created_at' => '2026-05-02T14:25:00-00:00',
            'click_id' => $clickA->click_uuid,
        ];
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $hmac = base64_encode(hash_hmac('sha256', $raw, 'e2e-shopify-secret', true));
        $this->call('POST', '/webhooks/shopify/orders', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SHOPIFY_HMAC_SHA256' => $hmac,
            'CONTENT_LENGTH' => (string) strlen($raw),
        ], $raw)->assertAccepted();

        $this->withHeaders($this->auth())->postJson('/api/v1/conversions/manual', [
            'campaign_id' => $campaignId,
            'amount' => 25.00,
            'converted_at' => '2026-05-02T14:28:00Z',
            'source' => 'manual_phone',
            'note' => 'E2E manual',
        ])->assertCreated();

        Visit::query()->where('campaign_id', $campaignId)->update(['lander_id' => $l1]);

        Artisan::call('tds:sync-taboola', [
            '--from' => '2026-05-02',
            '--to' => '2026-05-02',
        ]);
        Artisan::call('tds:aggregate-kpi', [
            '--from' => '2026-05-02',
            '--to' => '2026-05-02',
        ]);

        $c1 = Click::query()->where('campaign_id', $campaignId)->where('offer_id', $o1)->count();
        $c2 = Click::query()->where('campaign_id', $campaignId)->where('offer_id', $o2)->count();
        for ($i = $c1; $i < 25; $i++) {
            Click::query()->create([
                'click_uuid' => (string) Str::uuid(),
                'campaign_id' => $campaignId,
                'session_id' => $sessionUs->id,
                'offer_id' => $o1,
                'country_code' => 'US',
                'device_type' => 'desktop',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        for ($i = $c2; $i < 10; $i++) {
            Click::query()->create([
                'click_uuid' => (string) Str::uuid(),
                'campaign_id' => $campaignId,
                'session_id' => $sessionUs->id,
                'offer_id' => $o2,
                'country_code' => 'US',
                'device_type' => 'desktop',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Conversion::query()->create([
            'campaign_id' => $campaignId,
            'click_id' => null,
            'source' => 'bulk_seed',
            'external_order_id' => 'bulk-o2-'.Str::uuid(),
            'amount' => 200.00,
            'country_code' => 'US',
            'device_type' => 'desktop',
            'converted_at' => now(),
        ]);

        Artisan::call('tds:aggregate-kpi', [
            '--from' => '2026-05-02',
            '--to' => '2026-05-02',
        ]);

        $kpi = $this->withHeaders($this->auth())
            ->getJson('/api/v1/reports/kpi?campaign_id='.$campaignId.'&from=2026-05-02&to=2026-05-02')
            ->assertOk()
            ->json('data.current');

        $this->assertGreaterThanOrEqual(1, (int) ($kpi['conversions'] ?? 0));
        $this->assertGreaterThan(0.0, (float) ($kpi['revenue'] ?? 0));
        $this->assertGreaterThan(0.0, (float) ($kpi['cost'] ?? 0));

        $ab = $this->withHeaders($this->auth())
            ->getJson('/api/v1/reports/ab-tests?campaign_id='.$campaignId.'&from=2026-05-02&to=2026-05-02')
            ->assertOk()
            ->json('data');

        $this->assertArrayHasKey('recommendation', $ab);
        $this->assertNotEmpty($ab['variants']);
        $this->assertContains($ab['recommendation']['status'], [
            'recommended',
            'no_clear_winner',
            'insufficient_data',
        ], 'Winner pipeline should return a structured status.');
    }
}
