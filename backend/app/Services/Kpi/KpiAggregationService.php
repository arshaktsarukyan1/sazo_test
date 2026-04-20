<?php

namespace App\Services\Kpi;

use App\Models\Click;
use App\Models\Conversion;
use App\Models\CostEntry;
use App\Models\Kpi15mAggregate;
use App\Models\KpiDailyAggregate;
use App\Models\Visit;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Rolls raw events and cost_entries into kpi_15m_aggregates and kpi_daily_aggregates.
 */
final class KpiAggregationService
{
    public function recompute15MinuteRolling(): void
    {
        $hours = (int) config('tds.kpi_15m_lookback_hours', 6);
        $this->recompute15MinuteRange(now()->subHours($hours), now());
    }

    public function recompute15MinuteRange(Carbon $from, Carbon $to): void
    {
        $fromBucket = $this->alignTo15MinutesUtc($from);
        $toBucket = $this->alignTo15MinutesUtc($to);
        $fromStr = $fromBucket->toDateTimeString();
        $toStr = $toBucket->toDateTimeString();

        $visitBucket = $this->bucketStartSql('created_at');
        $clickBucket = $this->bucketStartSql('created_at');
        $conversionTs = 'COALESCE(converted_at, created_at)';
        $conversionBucket = $this->bucketStartSql($conversionTs);
        $costBucket = $this->bucketStartSql('bucket_start');

        $visitRows = Visit::query()->selectRaw("
                campaign_id,
                country_code,
                device_type,
                ({$visitBucket}) as bucket_start,
                COUNT(*) as visits
            ")
            ->whereRaw("({$visitBucket}) >= ? AND ({$visitBucket}) <= ?", [$fromStr, $toStr])
            ->groupByRaw('campaign_id, country_code, device_type, bucket_start')
            ->get();

        $clickRows = Click::query()->selectRaw("
                campaign_id,
                country_code,
                device_type,
                ({$clickBucket}) as bucket_start,
                COUNT(*) as clicks
            ")
            ->whereRaw("({$clickBucket}) >= ? AND ({$clickBucket}) <= ?", [$fromStr, $toStr])
            ->groupByRaw('campaign_id, country_code, device_type, bucket_start')
            ->get();

        $conversionRows = Conversion::query()->selectRaw("
                campaign_id,
                country_code,
                device_type,
                ({$conversionBucket}) as bucket_start,
                COUNT(*) as conversions,
                COALESCE(SUM(amount), 0) as revenue
            ")
            ->whereRaw("({$conversionBucket}) >= ? AND ({$conversionBucket}) <= ?", [$fromStr, $toStr])
            ->groupByRaw('campaign_id, country_code, device_type, bucket_start')
            ->get();

        $costRows = CostEntry::query()->selectRaw("
                campaign_id,
                country_code,
                NULL as device_type,
                ({$costBucket}) as bucket_start,
                COALESCE(SUM(amount), 0) as cost
            ")
            ->whereRaw("({$costBucket}) >= ? AND ({$costBucket}) <= ?", [$fromStr, $toStr])
            ->groupByRaw('campaign_id, country_code, bucket_start')
            ->get();

        $rows = [];
        foreach ([$visitRows, $clickRows, $conversionRows, $costRows] as $set) {
            foreach ($set as $row) {
                $key = implode('|', [
                    $row->campaign_id,
                    $row->country_code ?? '',
                    $row->device_type ?? '',
                    Carbon::parse($row->bucket_start)->toDateTimeString(),
                ]);
                $rows[$key] ??= [
                    'campaign_id' => $row->campaign_id,
                    'country_code' => $row->country_code,
                    'device_type' => $row->device_type,
                    'bucket_start' => Carbon::parse($row->bucket_start),
                    'visits' => 0,
                    'clicks' => 0,
                    'conversions' => 0,
                    'revenue' => 0.0,
                    'cost' => 0.0,
                ];
                $rows[$key]['visits'] += (int) ($row->visits ?? 0);
                $rows[$key]['clicks'] += (int) ($row->clicks ?? 0);
                $rows[$key]['conversions'] += (int) ($row->conversions ?? 0);
                $rows[$key]['revenue'] += (float) ($row->revenue ?? 0);
                $rows[$key]['cost'] += (float) ($row->cost ?? 0);
            }
        }

        foreach ($rows as $row) {
            Kpi15mAggregate::query()->updateOrCreate(
                [
                    'campaign_id' => $row['campaign_id'],
                    'country_code' => $row['country_code'],
                    'device_type' => $row['device_type'],
                    'bucket_start' => $row['bucket_start'],
                ],
                [
                    'visits' => $row['visits'],
                    'clicks' => $row['clicks'],
                    'conversions' => $row['conversions'],
                    'revenue' => $row['revenue'],
                    'cost' => $row['cost'],
                ]
            );
        }

        $this->rollupDailyForBucketWindow($fromBucket, $toBucket);
    }

    /**
     * Rebuild all 15m rows from raw data (expensive; used by seeders / backfills).
     */
    public function recomputeAll15MinuteFromRaw(): void
    {
        $minVisit = Visit::query()->min('created_at');
        $minClick = Click::query()->min('created_at');
        $minConv = Conversion::query()->min(DB::raw('COALESCE(converted_at, created_at)'));
        $minCost = CostEntry::query()->min('bucket_start');

        $candidates = array_filter([$minVisit, $minClick, $minConv, $minCost]);
        if ($candidates === []) {
            return;
        }

        $min = min(array_map(static fn ($v) => Carbon::parse($v), $candidates));
        $this->recompute15MinuteRange($min, now()->addMinutes(15));
    }

    public function rollupDailyForBucketWindow(Carbon $fromBucket, Carbon $toBucket): void
    {
        $fromDate = $fromBucket->copy()->utc()->startOfDay();
        $toDate = $toBucket->copy()->utc()->startOfDay();

        foreach (CarbonPeriod::create($fromDate, $toDate) as $day) {
            $this->rollupOneUtcDay(Carbon::parse($day));
        }
    }

    public function rollupDailyRecent(): void
    {
        $days = (int) config('tds.kpi_daily_rollup_lookback_days', 45);
        $end = now()->utc()->startOfDay();
        $start = $end->copy()->subDays($days);
        $this->rollupDailyForBucketWindow($start, $end->copy()->endOfDay());
    }

    private function rollupOneUtcDay(Carbon $utcDay): void
    {
        $start = $utcDay->copy()->utc()->startOfDay();
        $end = $utcDay->copy()->utc()->endOfDay();

        $dateExpr = match (DB::getDriverName()) {
            'pgsql', 'mysql', 'mariadb' => 'DATE(bucket_start)',
            'sqlite' => 'date(bucket_start)',
            default => throw new RuntimeException('Unsupported DB driver for KPI rollup: '.DB::getDriverName()),
        };

        $dailyRows = Kpi15mAggregate::query()->selectRaw("
                campaign_id,
                country_code,
                device_type,
                {$dateExpr} as bucket_date,
                SUM(visits) as visits,
                SUM(clicks) as clicks,
                SUM(conversions) as conversions,
                SUM(revenue) as revenue,
                SUM(cost) as cost
            ")
            ->whereBetween('bucket_start', [$start, $end])
            ->groupByRaw("campaign_id, country_code, device_type, {$dateExpr}")
            ->get();

        foreach ($dailyRows as $row) {
            KpiDailyAggregate::query()->updateOrCreate(
                [
                    'campaign_id' => $row->campaign_id,
                    'country_code' => $row->country_code,
                    'device_type' => $row->device_type,
                    'bucket_date' => Carbon::parse($row->bucket_date)->toDateString(),
                ],
                [
                    'visits' => (int) $row->visits,
                    'clicks' => (int) $row->clicks,
                    'conversions' => (int) $row->conversions,
                    'revenue' => (float) $row->revenue,
                    'cost' => (float) $row->cost,
                ]
            );
        }
    }

    private function alignTo15MinutesUtc(Carbon $t): Carbon
    {
        $ts = $t->copy()->utc()->getTimestamp();

        return Carbon::createFromTimestampUTC(intdiv($ts, 900) * 900);
    }

    private function bucketStartSql(string $columnSql): string
    {
        return match (DB::getDriverName()) {
            'pgsql' => "DATE_TRUNC('hour', {$columnSql}) + (FLOOR(EXTRACT(MINUTE FROM {$columnSql}) / 15) * INTERVAL '15 minute')",
            'mysql', 'mariadb' => "FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP({$columnSql}) / 900) * 900)",
            'sqlite' => "datetime((cast(strftime('%s', {$columnSql}) as integer) / 900) * 900, 'unixepoch')",
            default => throw new RuntimeException('Unsupported DB driver for KPI buckets: '.DB::getDriverName()),
        };
    }
}
