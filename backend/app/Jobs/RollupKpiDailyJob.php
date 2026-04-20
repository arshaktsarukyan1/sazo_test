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

final class RollupKpiDailyJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 900;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300, 900, 1800, 3600];

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

        $kpiAggregation->rollupDailyRecent();

        $metrics->increment('kpi.rollup_daily.success');
        $heartbeat->touchDaily();
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('kpi.rollup_daily.failed', [
            'exception' => $exception?->getMessage(),
        ]);

        Log::channel('alerts')->critical('kpi_rollup_daily_dead_letter', [
            'exception' => $exception?->getMessage(),
        ]);

        app(Metrics::class)->increment('kpi.rollup_daily.dead_letter');
    }
}
