<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Lander;
use App\Models\Offer;
use App\Models\TrafficSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignSplitValidationTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer test-internal-token'];
    }

    public function test_it_rejects_lander_split_when_active_sum_is_not_100_percent(): void
    {
        $domain = Domain::query()->create(['name' => 'split.test', 'status' => 'active']);
        $source = TrafficSource::query()->create(['name' => 'Taboola', 'slug' => 'taboola']);
        $landerA = Lander::query()->create(['name' => 'A', 'url' => 'https://example.com/a']);
        $landerB = Lander::query()->create(['name' => 'B', 'url' => 'https://example.com/b']);
        $offer = Offer::query()->create(['name' => 'Offer', 'url' => 'https://example.com/offer']);

        $response = $this->postJson('/api/v1/campaigns', [
            'domain_id' => $domain->id,
            'traffic_source_id' => $source->id,
            'name' => 'Split test campaign',
            'slug' => 'split-test-campaign',
            'status' => 'draft',
            'destination_url' => 'https://example.com/checkout',
            'landers' => [
                ['id' => $landerA->id, 'weight_percent' => 60, 'is_active' => true],
                ['id' => $landerB->id, 'weight_percent' => 30, 'is_active' => true],
            ],
            'offers' => [
                ['id' => $offer->id, 'weight_percent' => 100, 'is_active' => true],
            ],
        ], $this->authHeaders());

        $response->assertStatus(422)->assertJsonValidationErrors(['landers']);
    }

    public function test_it_accepts_valid_100_percent_splits_for_landers_and_offers(): void
    {
        $domain = Domain::query()->create(['name' => 'split-valid.test', 'status' => 'active']);
        $source = TrafficSource::query()->create(['name' => 'Meta', 'slug' => 'meta']);
        $landerA = Lander::query()->create(['name' => 'A', 'url' => 'https://example.com/a']);
        $landerB = Lander::query()->create(['name' => 'B', 'url' => 'https://example.com/b']);
        $offerA = Offer::query()->create(['name' => 'Offer A', 'url' => 'https://example.com/offer-a']);
        $offerB = Offer::query()->create(['name' => 'Offer B', 'url' => 'https://example.com/offer-b']);

        $response = $this->postJson('/api/v1/campaigns', [
            'domain_id' => $domain->id,
            'traffic_source_id' => $source->id,
            'name' => 'Split valid campaign',
            'slug' => 'split-valid-campaign',
            'status' => 'draft',
            'destination_url' => 'https://example.com/checkout',
            'landers' => [
                ['id' => $landerA->id, 'weight_percent' => 70, 'is_active' => true],
                ['id' => $landerB->id, 'weight_percent' => 30, 'is_active' => true],
            ],
            'offers' => [
                ['id' => $offerA->id, 'weight_percent' => 80, 'is_active' => true],
                ['id' => $offerB->id, 'weight_percent' => 20, 'is_active' => true],
            ],
        ], $this->authHeaders());

        $response->assertCreated();
        $this->assertDatabaseCount('campaign_landers', 2);
        $this->assertDatabaseCount('campaign_offers', 2);
    }
}
