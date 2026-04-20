<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Domain;
use App\Models\Lander;
use App\Models\TrafficSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RedirectDomainHostTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_succeeds_when_request_host_matches_assigned_active_domain(): void
    {
        $source = TrafficSource::query()->create(['name' => 'Src', 'slug' => 'src-'.Str::uuid()]);
        $domain = Domain::query()->create([
            'name' => 'track-brand.example',
            'status' => 'active',
            'is_active' => true,
        ]);
        $campaign = Campaign::query()->create([
            'domain_id' => $domain->id,
            'traffic_source_id' => $source->id,
            'name' => 'Branded',
            'slug' => 'branded-'.Str::uuid(),
            'status' => 'active',
            'destination_url' => 'https://fallback.example/out',
        ]);
        $lander = Lander::query()->create(['name' => 'L', 'url' => 'https://lander.example/p']);
        $campaign->landers()->sync([
            $lander->id => ['weight_percent' => 100, 'is_active' => true],
        ]);

        $this->get('http://track-brand.example/api/campaign/'.$campaign->slug)
            ->assertRedirect();
    }

    public function test_redirect_fails_when_host_does_not_match_assigned_domain(): void
    {
        $source = TrafficSource::query()->create(['name' => 'Src2', 'slug' => 'src2-'.Str::uuid()]);
        $domain = Domain::query()->create([
            'name' => 'allowed.example',
            'status' => 'active',
            'is_active' => true,
        ]);
        $campaign = Campaign::query()->create([
            'domain_id' => $domain->id,
            'traffic_source_id' => $source->id,
            'name' => 'Locked',
            'slug' => 'locked-'.Str::uuid(),
            'status' => 'active',
            'destination_url' => 'https://fallback.example/out',
        ]);
        $lander = Lander::query()->create(['name' => 'L2', 'url' => 'https://lander2.example/p']);
        $campaign->landers()->sync([
            $lander->id => ['weight_percent' => 100, 'is_active' => true],
        ]);

        $this->get('http://wrong-host.example/api/campaign/'.$campaign->slug)->assertNotFound();
    }

    public function test_redirect_fails_when_domain_is_pending_even_if_host_matches(): void
    {
        $source = TrafficSource::query()->create(['name' => 'Src3', 'slug' => 'src3-'.Str::uuid()]);
        $domain = Domain::query()->create([
            'name' => 'pending.example',
            'status' => 'pending',
            'is_active' => true,
        ]);
        $campaign = Campaign::query()->create([
            'domain_id' => $domain->id,
            'traffic_source_id' => $source->id,
            'name' => 'Pending dom',
            'slug' => 'pending-dom-'.Str::uuid(),
            'status' => 'active',
            'destination_url' => 'https://fallback.example/out',
        ]);
        $lander = Lander::query()->create(['name' => 'L3', 'url' => 'https://lander3.example/p']);
        $campaign->landers()->sync([
            $lander->id => ['weight_percent' => 100, 'is_active' => true],
        ]);

        $this->get('http://pending.example/api/campaign/'.$campaign->slug)->assertNotFound();
    }
}
