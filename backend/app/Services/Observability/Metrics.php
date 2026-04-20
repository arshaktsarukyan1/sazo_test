<?php

namespace App\Services\Observability;

use Illuminate\Support\Facades\Cache;

/**
 * Lightweight counters / latency samples (cache-backed) for internal MVP observability.
 */
final class Metrics
{
    private const PREFIX = 'tds_metrics:';

    public function increment(string $name, int $by = 1): void
    {
        $key = self::PREFIX.'counter:'.$name;
        Cache::increment($key, $by);
    }

    public function recordLatencyMs(string $name, float $milliseconds): void
    {
        $sumKey = self::PREFIX.'latency_sum_ms:'.$name;
        $cntKey = self::PREFIX.'latency_count:'.$name;
        Cache::increment($sumKey, (int) round($milliseconds));
        Cache::increment($cntKey);
    }

    public function setGauge(string $name, float|int|string $value): void
    {
        Cache::forever(self::PREFIX.'gauge:'.$name, $value);
    }

    /**
     * @return array{counter?: int, latency_avg_ms?: float|null, gauge?: mixed}
     */
    public function read(string $name): array
    {
        $counter = (int) Cache::get(self::PREFIX.'counter:'.$name, 0);
        $sum = (int) Cache::get(self::PREFIX.'latency_sum_ms:'.$name, 0);
        $cnt = (int) Cache::get(self::PREFIX.'latency_count:'.$name, 0);

        return [
            'counter' => $counter,
            'latency_avg_ms' => $cnt > 0 ? round($sum / $cnt, 3) : null,
            'gauge' => Cache::get(self::PREFIX.'gauge:'.$name),
        ];
    }
}
