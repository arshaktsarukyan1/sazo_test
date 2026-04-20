<?php

namespace Tests\Unit;

use App\Services\Cost\TaboolaCostAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TaboolaCostAdapterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::fake();
        Config::set('tds.taboola', [
            'account_id' => 'demo-account',
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'token_cache_ttl_seconds' => 120,
        ]);
    }

    public function test_fetch_spend_parses_campaign_day_breakdown(): void
    {
        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            if (str_contains($request->url(), '/backstage/oauth/token')) {
                return Http::response([
                    'access_token' => 'test-token',
                    'expires_in' => 3600,
                ], 200);
            }
            if (str_contains($request->url(), 'campaign_day_breakdown')) {
                $auth = $request->header('Authorization');
                $authStr = is_array($auth) ? (string) ($auth[0] ?? '') : (string) $auth;
                $this->assertStringStartsWith('Bearer test-token', $authStr);

                return Http::response([
                    'results' => [
                        [
                            'date' => '2026-04-10 00:00:00.0',
                            'spent' => '12.34',
                            'campaign' => '9001',
                        ],
                        [
                            'date' => '2026-04-11 00:00:00.0',
                            'spent' => '0.66',
                            'country_code' => 'US',
                        ],
                    ],
                ], 200);
            }

            return Http::response('unexpected URL: '.$request->url(), 500);
        });

        $adapter = new TaboolaCostAdapter;
        $rows = $adapter->fetchSpendByDay('2026-04-10', '2026-04-11', ['9001']);

        $this->assertCount(2, $rows);
        $this->assertSame('9001', $rows[0]->externalCampaignId);
        $this->assertSame('2026-04-10', $rows[0]->day);
        $this->assertNull($rows[0]->countryCode);
        $this->assertEqualsWithDelta(12.34, $rows[0]->amount, 0.001);

        $this->assertSame('US', $rows[1]->countryCode);
        $this->assertEqualsWithDelta(0.66, $rows[1]->amount, 0.001);
    }

    public function test_returns_empty_when_not_configured(): void
    {
        Config::set('tds.taboola', [
            'account_id' => '',
            'client_id' => '',
            'client_secret' => '',
        ]);

        $adapter = new TaboolaCostAdapter;
        $rows = $adapter->fetchSpendByDay('2026-04-10', '2026-04-10', ['1']);

        $this->assertSame([], $rows);
        Http::assertNothingSent();
    }
}
