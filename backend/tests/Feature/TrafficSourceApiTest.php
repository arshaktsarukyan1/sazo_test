<?php

namespace Tests\Feature;

use App\Models\TrafficSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrafficSourceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_active_traffic_sources_with_token(): void
    {
        TrafficSource::query()->create([
            'name' => 'Inactive',
            'slug' => 'inactive',
            'is_active' => false,
        ]);
        TrafficSource::query()->create([
            'name' => 'Active',
            'slug' => 'active',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/traffic-sources', [
            'Authorization' => 'Bearer test-internal-token',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.0.slug', 'active');
        $response->assertJsonCount(1, 'data');
    }
}
