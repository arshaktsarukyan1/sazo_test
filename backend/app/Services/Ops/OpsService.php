<?php

namespace App\Services\Ops;

use App\Models\CostSyncRun;
use Illuminate\Support\Facades\Cache;

final class OpsService
{
    /**
     * @return array{data: \Illuminate\Support\Collection<int, CostSyncRun>, heartbeat: array<string, mixed>}
     */
    public function syncRunsWithHeartbeat(): array
    {
        $runs = CostSyncRun::query()
            ->latest('id')
            ->limit(50)
            ->get();

        return [
            'data' => $runs,
            'heartbeat' => [
                'kpi_15m_last_success_at' => Cache::get('ops:kpi_15m:last_success_at'),
                'kpi_daily_last_success_at' => Cache::get('ops:kpi_daily:last_success_at'),
            ],
        ];
    }
}
