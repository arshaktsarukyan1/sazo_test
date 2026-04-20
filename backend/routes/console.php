<?php

use App\Jobs\AggregateKpi15mJob;
use App\Jobs\RollupKpiDailyJob;
use App\Models\Campaign;
use App\Models\IngestionIdempotencyKey;
use App\Models\CostEntry;
use App\Models\Kpi15mAggregate;
use App\Models\KpiDailyAggregate;
use App\Models\Click;
use App\Models\Conversion;
use App\Models\Visit;
use App\Services\Cost\TaboolaCostSyncService;
use App\Services\Ingestion\IngestionIdempotency;
use App\Services\Kpi\KpiAggregationService;
use App\Services\Observability\Metrics;
use Carbon\Carbon;
use Database\Seeders\PhaseOneCoreSeeder;
use Database\Seeders\SyntheticEventSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('tds:sync-taboola {--from=} {--to=} {--idempotency-key=}', function (): void {
    $fromOpt = $this->option('from');
    $toOpt = $this->option('to');

    $from = $fromOpt
        ? Carbon::parse((string) $fromOpt)->utc()->startOfDay()
        : now()->subDay()->utc()->startOfDay();
    $to = $toOpt
        ? Carbon::parse((string) $toOpt)->utc()->endOfDay()
        : now()->utc()->endOfDay();

    $idemKey = $this->option('idempotency-key');
    $idem = app(IngestionIdempotency::class);
    $scope = IngestionIdempotencyKey::SCOPE_TABOOLA_COST_SYNC;

    if (is_string($idemKey) && $idemKey !== '') {
        if (! $idem->tryAcquire($scope, $idemKey)) {
            $this->warn('Duplicate idempotency key — skipping sync.');

            return;
        }
    }

    try {
        $run = app(TaboolaCostSyncService::class)->sync($from, $to);
        app(Metrics::class)->increment('sync.taboola.success');
        $this->info("Taboola sync finished: run_id={$run->id} status={$run->status} rows={$run->rows_upserted}");
    } catch (\Throwable $e) {
        if (is_string($idemKey) && $idemKey !== '') {
            $idem->release($scope, $idemKey);
        }
        app(Metrics::class)->increment('sync.taboola.errors');

        throw $e;
    }
});

Schedule::job(new AggregateKpi15mJob)->everyFifteenMinutes()->withoutOverlapping();

Schedule::job(new RollupKpiDailyJob)->dailyAt('00:35')->withoutOverlapping();

Schedule::command('tds:ops-monitor-kpi-sync')->everyFiveMinutes()->withoutOverlapping();

Artisan::command('tds:aggregate-kpi {--from=} {--to=} {--full}', function (): void {
    $svc = app(KpiAggregationService::class);

    if ($this->option('full')) {
        $svc->recomputeAll15MinuteFromRaw();
        $svc->rollupDailyRecent();
    } elseif ($this->option('from') && $this->option('to')) {
        $svc->recompute15MinuteRange(
            Carbon::parse((string) $this->option('from'))->utc(),
            Carbon::parse((string) $this->option('to'))->utc()
        );
        $svc->rollupDailyRecent();
    } else {
        $svc->recompute15MinuteRolling();
        $svc->rollupDailyRecent();
    }

    $fifteenCount = Kpi15mAggregate::query()->count();
    $dailyCount = KpiDailyAggregate::query()->count();

    $this->info("KPI aggregation complete. 15m rows={$fifteenCount}, daily rows={$dailyCount}");
});

Artisan::command('tds:rebuild-kpi {--from=} {--to=}', function (): void {
    $svc = app(KpiAggregationService::class);
    $svc->recomputeAll15MinuteFromRaw();
    $svc->rollupDailyRecent();
    $this->info('KPI rebuild complete (all 15m buckets from raw + daily rollup).');
});

Artisan::command('tds:seed-phase1', function (): void {
    $this->call('db:seed', ['--class' => PhaseOneCoreSeeder::class, '--force' => true]);
    $this->call('db:seed', ['--class' => SyntheticEventSeeder::class, '--force' => true]);
    $this->call('tds:aggregate-kpi');

    $campaign = Campaign::query()->with(['landers', 'offers'])->first();
    if ($campaign === null) {
        $this->error('No campaign created.');
        return;
    }

    $this->info(sprintf(
        'Seed complete: campaign=%s landers=%d offers=%d visits=%d clicks=%d conversions=%d costs=%d',
        $campaign->slug,
        $campaign->landers()->count(),
        $campaign->offers()->count(),
        Visit::query()->count(),
        Click::query()->count(),
        Conversion::query()->count(),
        CostEntry::query()->count()
    ));
});

Artisan::command('tds:verify-indexes', function (): void {
    $driver = DB::getDriverName();
    if (! in_array($driver, ['pgsql', 'mysql', 'mariadb'], true)) {
        $this->warn("Index verification supports pgsql, mysql, and mariadb. Current driver: {$driver}");

        return;
    }

    $expected = [
        'visits' => ['visits_campaign_id_created_at_index', 'visits_campaign_geo_device_created_idx'],
        'clicks' => ['clicks_campaign_id_created_at_index', 'clicks_campaign_geo_device_created_idx'],
        'conversions' => ['conversions_campaign_id_created_at_index', 'conversions_campaign_geo_device_created_idx'],
    ];

    foreach ($expected as $table => $indexes) {
        if ($driver === 'pgsql') {
            $rows = DB::select("SELECT indexname FROM pg_indexes WHERE schemaname = 'public' AND tablename = ?", [$table]);
            $actual = collect($rows)->pluck('indexname')->map(fn ($n) => strtolower((string) $n))->all();
        } else {
            $rows = DB::select(
                'SELECT DISTINCT INDEX_NAME AS indexname FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ?',
                [$table]
            );
            $actual = collect($rows)->pluck('indexname')->map(fn ($n) => strtolower((string) $n))->all();
        }

        $expectedLower = array_map(strtolower(...), $indexes);
        $missing = array_values(array_diff($expectedLower, $actual));

        if (count($missing) > 0) {
            $this->error($table . ' missing indexes: ' . implode(', ', $missing));
            continue;
        }

        $this->info($table . ' indexes OK');
    }
});
