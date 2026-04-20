<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Domain;
use App\Models\Lander;
use App\Models\Offer;
use App\Models\TrafficSource;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PhaseOneCoreSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $domain = Domain::query()->firstOrCreate(
                ['name' => 'tds.local'],
                ['status' => 'active', 'is_active' => true]
            );

            $source = TrafficSource::query()->firstOrCreate(
                ['slug' => 'taboola'],
                ['name' => 'Taboola', 'is_active' => true]
            );

            $campaign = Campaign::query()->updateOrCreate(
                ['slug' => 'sample-campaign'],
                [
                    'domain_id' => $domain->id,
                    'traffic_source_id' => $source->id,
                    'name' => 'Sample Campaign',
                    'status' => 'active',
                    'destination_url' => 'https://example.com/checkout',
                    'timezone' => 'UTC',
                    'daily_budget' => 100,
                    'monthly_budget' => 2000,
                ]
            );

            $landers = [
                Lander::query()->firstOrCreate(['url' => 'https://example.com/lander-a'], ['name' => 'Lander A']),
                Lander::query()->firstOrCreate(['url' => 'https://example.com/lander-b'], ['name' => 'Lander B']),
                Lander::query()->firstOrCreate(['url' => 'https://example.com/lander-c'], ['name' => 'Lander C']),
            ];

            $offers = [
                Offer::query()->firstOrCreate(['url' => 'https://example.com/offer-a'], ['name' => 'Offer A']),
                Offer::query()->firstOrCreate(['url' => 'https://example.com/offer-b'], ['name' => 'Offer B']),
            ];

            $campaign->landers()->sync([
                $landers[0]->id => ['weight_percent' => 50, 'is_active' => true],
                $landers[1]->id => ['weight_percent' => 30, 'is_active' => true],
                $landers[2]->id => ['weight_percent' => 20, 'is_active' => true],
            ]);

            $campaign->offers()->sync([
                $offers[0]->id => ['weight_percent' => 70, 'is_active' => true],
                $offers[1]->id => ['weight_percent' => 30, 'is_active' => true],
            ]);
        });
    }
}
