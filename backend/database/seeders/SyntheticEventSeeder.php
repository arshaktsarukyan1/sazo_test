<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Click;
use App\Models\Conversion;
use App\Models\CostEntry;
use App\Models\Session;
use App\Models\Visit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyntheticEventSeeder extends Seeder
{
    public function run(): void
    {
        $campaign = Campaign::query()->with(['landers', 'offers'])->firstOrFail();
        $lander = $campaign->landers()->firstOrFail();
        $offer = $campaign->offers()->firstOrFail();

        DB::transaction(function () use ($campaign, $lander, $offer): void {
            for ($i = 0; $i < 50; $i++) {
                $ts = Carbon::now()->subMinutes(random_int(0, 120));
                $country = $i % 2 === 0 ? 'US' : 'GB';
                $device = $i % 3 === 0 ? 'mobile' : 'desktop';

                $session = Session::query()->create([
                    'session_uuid' => (string) Str::uuid(),
                    'ip' => '10.0.0.' . ($i + 1),
                    'country_code' => $country,
                    'region' => 'NA',
                    'city' => 'Test City',
                    'device_type' => $device,
                    'browser' => 'Chrome',
                    'os' => 'Linux',
                    'language' => 'en',
                ]);

                Visit::query()->create([
                    'campaign_id' => $campaign->id,
                    'session_id' => $session->id,
                    'lander_id' => $lander->id,
                    'country_code' => $country,
                    'device_type' => $device,
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ]);

                $click = Click::query()->create([
                    'click_uuid' => (string) Str::uuid(),
                    'campaign_id' => $campaign->id,
                    'session_id' => $session->id,
                    'offer_id' => $offer->id,
                    'country_code' => $country,
                    'device_type' => $device,
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ]);

                if ($i % 5 === 0) {
                    Conversion::query()->create([
                        'campaign_id' => $campaign->id,
                        'click_id' => $click->id,
                        'source' => 'manual',
                        'external_order_id' => 'ORDER-' . $i . '-' . Str::random(6),
                        'amount' => 25.50,
                        'country_code' => $country,
                        'device_type' => $device,
                        'converted_at' => $ts->copy()->addMinutes(3),
                        'created_at' => $ts->copy()->addMinutes(3),
                        'updated_at' => $ts->copy()->addMinutes(3),
                    ]);
                }
            }

            CostEntry::query()->create([
                'campaign_id' => $campaign->id,
                'source' => 'taboola',
                'external_campaign_id' => 'TAB-' . $campaign->id,
                'country_code' => 'US',
                'amount' => 40.00,
                'bucket_start' => Carbon::now()->startOfHour(),
            ]);
        });
    }
}
