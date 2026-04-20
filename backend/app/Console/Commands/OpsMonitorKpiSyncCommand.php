<?php

namespace App\Console\Commands;

use App\Services\Observability\KpiSyncHeartbeat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class OpsMonitorKpiSyncCommand extends Command
{
    protected $signature = 'tds:ops-monitor-kpi-sync';

    protected $description = 'Alert when the scheduled 15-minute KPI aggregate has not succeeded recently';

    public function handle(KpiSyncHeartbeat $heartbeat): int
    {
        $last = $heartbeat->last15mAt();
        if ($last === null) {
            $this->comment('No KPI 15m heartbeat recorded yet; skipping alert until the first successful run.');

            return self::SUCCESS;
        }

        $afterMinutes = (int) config('tds.kpi_sync_alert_after_minutes', 22);
        if ($last->greaterThanOrEqualTo(now()->subMinutes($afterMinutes))) {
            return self::SUCCESS;
        }

        Log::channel('alerts')->critical('kpi_15m_sync_window_missed', [
            'last_success_at' => $last->toIso8601String(),
            'threshold_minutes' => $afterMinutes,
        ]);

        return self::FAILURE;
    }
}
