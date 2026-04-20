<?php

namespace App\Services\Observability;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

final class KpiSyncHeartbeat
{
    private const KEY_15M = 'ops:kpi_15m:last_success_at';

    private const KEY_DAILY = 'ops:kpi_daily:last_success_at';

    public function touch15m(): void
    {
        Cache::forever(self::KEY_15M, now()->toIso8601String());
    }

    public function touchDaily(): void
    {
        Cache::forever(self::KEY_DAILY, now()->toIso8601String());
    }

    public function last15mAt(): ?Carbon
    {
        $raw = Cache::get(self::KEY_15M);
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    public function mark15mStaleForSyntheticTest(): void
    {
        Cache::forever(self::KEY_15M, now()->subHours(2)->toIso8601String());
    }
}
