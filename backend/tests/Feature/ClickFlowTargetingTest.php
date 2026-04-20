<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Offer;
use App\Models\Session;
use App\Models\TargetingRule;
use App\Models\TrafficSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ClickFlowTargetingTest extends TestCase
{
    use RefreshDatabase;

    public function test_fr_mobile_and_be_desktop_route_to_expected_offers(): void
    {
        $trafficSource = TrafficSource::query()->create(['name' => 'Native', 'slug' => 'native']);
        $campaign = Campaign::query()->create([
            'traffic_source_id' => $trafficSource->id,
            'name' => 'Targeting campaign',
            'slug' => 'targeting-campaign',
            'status' => 'active',
            'destination_url' => 'https://fallback.example',
        ]);

        $offerFrMobile = Offer::query()->create(['name' => 'FR Mobile', 'url' => 'https://offer.example/fr-mobile']);
        $offerBeDesktop = Offer::query()->create(['name' => 'BE Desktop', 'url' => 'https://offer.example/be-desktop']);
        $offerDefault = Offer::query()->create(['name' => 'Default', 'url' => 'https://offer.example/default']);

        $campaign->offers()->sync([
            $offerDefault->id => ['weight_percent' => 100, 'is_active' => true],
        ]);

        TargetingRule::query()->create([
            'campaign_id' => $campaign->id,
            'offer_id' => $offerFrMobile->id,
            'country_code' => 'FR',
            'device_type' => 'mobile',
            'priority' => 1,
            'is_active' => true,
        ]);

        TargetingRule::query()->create([
            'campaign_id' => $campaign->id,
            'offer_id' => $offerBeDesktop->id,
            'country_code' => 'BE',
            'device_type' => 'desktop',
            'priority' => 1,
            'is_active' => true,
        ]);

        Session::query()->create([
            'session_uuid' => 'fr-mobile-session',
            'country_code' => 'FR',
            'device_type' => 'mobile',
            'ip' => '127.0.0.1',
        ]);

        Session::query()->create([
            'session_uuid' => 'be-desktop-session',
            'country_code' => 'BE',
            'device_type' => 'desktop',
            'ip' => '127.0.0.1',
        ]);

        $frResponse = $this->get('/api/click?campaign=' . $campaign->slug . '&sid=fr-mobile-session');
        $beResponse = $this->get('/api/click?campaign=' . $campaign->slug . '&sid=be-desktop-session');

        $this->assertStringContainsString('offer.example/fr-mobile', (string) $frResponse->headers->get('Location'));
        $this->assertStringContainsString('offer.example/be-desktop', (string) $beResponse->headers->get('Location'));
    }

    public function test_missing_token_uses_safe_fallback_and_logs_anomaly(): void
    {
        Log::spy();

        $trafficSource = TrafficSource::query()->create(['name' => 'Push', 'slug' => 'push']);
        $campaign = Campaign::query()->create([
            'traffic_source_id' => $trafficSource->id,
            'name' => 'Fallback campaign',
            'slug' => 'fallback-campaign',
            'status' => 'active',
            'destination_url' => 'https://fallback.example',
        ]);

        $offerDefault = Offer::query()->create(['name' => 'Default', 'url' => 'https://offer.example/default']);
        $campaign->offers()->sync([
            $offerDefault->id => ['weight_percent' => 100, 'is_active' => true],
        ]);

        $response = $this->get('/api/click?campaign=' . $campaign->slug);
        $this->assertStringContainsString('offer.example/default', (string) $response->headers->get('Location'));

        Log::shouldHaveReceived('warning')->once();
    }
}
