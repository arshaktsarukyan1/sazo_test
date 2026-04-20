<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Click;
use App\Models\Offer;
use App\Models\Session;
use App\Models\TrafficSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportAbTest extends TestCase
{
    use RefreshDatabase;

    private function internalHeaders(): array
    {
        return ['Authorization' => 'Bearer test-internal-token'];
    }

    public function test_ab_tests_validation_requires_campaign_id(): void
    {
        $this->withHeaders($this->internalHeaders())
            ->getJson('/api/v1/reports/ab-tests')
            ->assertUnprocessable();
    }

    public function test_ab_tests_returns_insufficient_data_for_low_traffic(): void
    {
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

        $offer = Offer::query()->create([
            'name' => 'O',
            'url' => 'https://example.com/o',
        ]);

        Click::query()->create([
            'click_uuid' => (string) Str::uuid(),
            'campaign_id' => $campaign->id,
            'session_id' => $session->id,
            'offer_id' => $offer->id,
            'country_code' => 'US',
            'device_type' => 'desktop',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withHeaders($this->internalHeaders())
            ->getJson('/api/v1/reports/ab-tests?campaign_id='.$campaign->id)
            ->assertOk()
            ->assertJsonPath('data.recommendation.status', 'insufficient_data');
    }
}
