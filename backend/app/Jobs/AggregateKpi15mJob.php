<?php

namespace App\Jobs;

use App\Services\Kpi\KpiAggregationService;
use App\Services\Observability\KpiSyncHeartbeat;
use App\Services\Observability\Metrics;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class AggregateKpi15mJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 600;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120, 300, 600, 1200];

    public function handle(
        KpiAggregationService $kpiAggregation,
        Metrics $metrics,
        KpiSyncHeartbeat $heartbeat,
    ): void {
        if ($this->job !== null && isset($this->job->payload()['pushedAt'])) {
            $metrics->recordLatencyMs(
                'queue.lag_ms',
                (microtime(true) - (float) $this->job->payload()['pushedAt']) * 1000.0
            );
        }

        Log::withContext([
            'queue_job' => static::class,
            'queue_correlation' => (string) Str::uuid(),
        ]);

        $kpiAggregation->recompute15MinuteRolling();

        $metrics->increment('kpi.aggregate_15m.success');
        $heartbeat->touch15m();
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('kpi.aggregate_15m.failed', [
            'exception' => $exception?->getMessage(),
        ]);

        Log::channel('alerts')->critical('kpi_aggregate_15m_dead_letter', [
            'exception' => $exception?->getMessage(),
        ]);

        app(Metrics::class)->increment('kpi.aggregate_15m.dead_letter');
    }
}
