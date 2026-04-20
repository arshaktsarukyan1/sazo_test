<?php

namespace Database\Seeders;

use App\Models\Kpi15mAggregate;
use App\Models\KpiDailyAggregate;
use App\Services\Kpi\KpiAggregationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KpiAggregationSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            Kpi15mAggregate::query()->delete();
            KpiDailyAggregate::query()->delete();

            app(KpiAggregationService::class)->recomputeAll15MinuteFromRaw();
        });
    }
}
