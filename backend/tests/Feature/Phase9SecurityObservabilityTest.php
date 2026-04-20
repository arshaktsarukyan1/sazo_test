<?php

namespace Tests\Feature;

use App\Jobs\AggregateKpi15mJob;
use App\Jobs\RollupKpiDailyJob;
use App\Models\Campaign;
use App\Models\Lander;
use App\Models\TrafficSource;
use App\Services\Observability\KpiSyncHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class Phase9SecurityObservabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_redirect_rate_limit_returns_429(): void
    {
        Config::set('tds.redirect_rate_limit_per_minute', 3);

        $source = TrafficSource::query()->create(['name' => 'RL', 'slug' => 'rl-'.uniqid('', true)]);
        $campaign = Campaign::query()->create([
            'traffic_source_id' => $source->id,
            'name' => 'RL camp',
            'slug' => 'rl-camp',
            'status' => 'active',
            'destination_url' => 'https://fallback.example/out',
        ]);
        $lander = Lander::query()->create(['name' => 'L', 'url' => 'https://lander.example/p']);
        $campaign->landers()->sync([
            $lander->id => ['weight_percent' => 100, 'is_active' => true],
        ]);

        for ($i = 0; $i < 3; $i++) {
            $this->get('/api/campaign/'.$campaign->slug)->assertRedirect();
        }

        $this->get('/api/campaign/'.$campaign->slug)->assertStatus(429);
    }

    public function test_validation_errors_include_correlation_id(): void
    {
        $this->getJson('/api/click')
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors', 'correlation_id']);
    }

    public function test_synthetic_ops_alert_command_succeeds(): void
    {
        $this->artisan('tds:ops-synthetic-alert')->assertSuccessful();
    }

    public function test_ops_monitor_exits_failure_on_stale_heartbeat(): void
    {
        app(KpiSyncHeartbeat::class)->mark15mStaleForSyntheticTest();

        $this->artisan('tds:ops-monitor-kpi-sync')->assertFailed();
    }

    public function test_kpi_jobs_declare_retry_backoff_and_timeouts(): void
    {
        $a = new AggregateKpi15mJob;
        $this->assertSame(5, $a->tries);
        $this->assertSame(600, $a->timeout);
        $this->assertSame([30, 120, 300, 600, 1200], $a->backoff);

        $d = new RollupKpiDailyJob;
        $this->assertSame(5, $d->tries);
        $this->assertSame(900, $d->timeout);
        $this->assertSame([60, 300, 900, 1800, 3600], $d->backoff);
    }
}
