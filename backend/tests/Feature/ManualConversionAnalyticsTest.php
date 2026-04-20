<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\TrafficSource;
use Database\Seeders\KpiAggregationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class ManualConversionAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function internalHeaders(): array
    {
        return ['Authorization' => 'Bearer test-internal-token'];
    }

    public function test_manual_conversion_appears_in_campaign_kpi_after_aggregation(): void
    {
        $source = TrafficSource::query()->create([
            'name' => 'Manual src',
            'slug' => 'manual-src-'.Str::uuid(),
            'is_active' => true,
        ]);

        $campaign = Campaign::query()->create([
            'traffic_source_id' => $source->id,
            'name' => 'Manual KPI campaign',
            'slug' => 'manual-kpi-'.Str::uuid(),
            'status' => 'active',
            'destination_url' => 'https://example.com/out',
        ]);

        $convertedAt = now()->subHour();

        $this->withHeaders($this->internalHeaders())
            ->postJson('/api/v1/conversions/manual', [
                'campaign_id' => $campaign->id,
                'amount' => 77.25,
                'converted_at' => $convertedAt->toIso8601String(),
                'note' => 'Phone order',
                'source' => 'manual_adjustment',
            ])
            ->assertCreated()
            ->assertJsonPath('data.campaign_id', $campaign->id)
            ->assertJsonPath('data.amount', 77.25);

        Artisan::call('db:seed', ['--class' => KpiAggregationSeeder::class, '--force' => true]);

        $from = $convertedAt->copy()->startOfDay()->toDateString();
        $to = $convertedAt->copy()->endOfDay()->toDateString();

        $kpi = $this->withHeaders($this->internalHeaders())
            ->getJson("/api/v1/reports/kpi?campaign_id={$campaign->id}&from={$from}&to={$to}")
            ->assertOk()
            ->json('data.current');

        $this->assertGreaterThanOrEqual(1, (int) ($kpi['conversions'] ?? 0));
        $this->assertGreaterThanOrEqual(77.25, (float) ($kpi['revenue'] ?? 0));
    }
}
