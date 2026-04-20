<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Lander;
use App\Models\TrafficSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RedirectFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_hits_distribute_by_split(): void
    {
        $trafficSource = TrafficSource::query()->create(['name' => 'Meta', 'slug' => 'meta']);
        $campaign = Campaign::query()->create([
            'traffic_source_id' => $trafficSource->id,
            'name' => 'Redirect campaign',
            'slug' => 'redirect-campaign',
            'status' => 'active',
            'destination_url' => 'https://fallback.example/checkout',
        ]);

        $landerA = Lander::query()->create(['name' => 'Lander A', 'url' => 'https://a.example/lander']);
        $landerB = Lander::query()->create(['name' => 'Lander B', 'url' => 'https://b.example/lander']);

        $campaign->landers()->sync([
            $landerA->id => ['weight_percent' => 70, 'is_active' => true],
            $landerB->id => ['weight_percent' => 30, 'is_active' => true],
        ]);

        $counts = ['a' => 0, 'b' => 0];
        for ($i = 0; $i < 300; $i++) {
            $response = $this->get('/api/campaign/' . $campaign->slug);
            $location = (string) $response->headers->get('Location');

            if (str_contains($location, 'a.example/lander')) {
                $counts['a']++;
            }
            if (str_contains($location, 'b.example/lander')) {
                $counts['b']++;
            }
        }

        $ratioA = ($counts['a'] / 300) * 100;
        $ratioB = ($counts['b'] / 300) * 100;

        $this->assertTrue($ratioA >= 60 && $ratioA <= 80, 'Lander A distribution out of tolerance.');
        $this->assertTrue($ratioB >= 20 && $ratioB <= 40, 'Lander B distribution out of tolerance.');
    }

    public function test_deactivated_landers_are_never_selected(): void
    {
        $trafficSource = TrafficSource::query()->create(['name' => 'Google', 'slug' => 'google']);
        $campaign = Campaign::query()->create([
            'traffic_source_id' => $trafficSource->id,
            'name' => 'Inactive lander campaign',
            'slug' => 'inactive-lander-campaign',
            'status' => 'active',
            'destination_url' => 'https://fallback.example/checkout',
        ]);

        $activeLander = Lander::query()->create(['name' => 'Active', 'url' => 'https://active.example/lander']);
        $inactiveLander = Lander::query()->create(['name' => 'Inactive', 'url' => 'https://inactive.example/lander']);

        $campaign->landers()->sync([
            $activeLander->id => ['weight_percent' => 100, 'is_active' => true],
            $inactiveLander->id => ['weight_percent' => 100, 'is_active' => false],
        ]);

        for ($i = 0; $i < 40; $i++) {
            $response = $this->get('/api/campaign/' . $campaign->slug);
            $location = (string) $response->headers->get('Location');
            $this->assertStringContainsString('active.example/lander', $location);
            $this->assertStringNotContainsString('inactive.example/lander', $location);
        }

        $this->assertGreaterThan(0, DB::table('visits')->count());
    }

    public function test_legacy_r_path_still_redirects(): void
    {
        $trafficSource = TrafficSource::query()->create(['name' => 'Legacy', 'slug' => 'legacy']);
        $campaign = Campaign::query()->create([
            'traffic_source_id' => $trafficSource->id,
            'name' => 'Legacy path',
            'slug' => 'legacy-path-campaign',
            'status' => 'active',
            'destination_url' => 'https://fallback.example/checkout',
        ]);
        $lander = Lander::query()->create(['name' => 'L', 'url' => 'https://lander.example/p']);
        $campaign->landers()->sync([
            $lander->id => ['weight_percent' => 100, 'is_active' => true],
        ]);

        $response = $this->get('/api/r/' . $campaign->slug);
        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('https://lander.example/p?', $location);
        $this->assertStringContainsString('sid=', $location);
    }
}
