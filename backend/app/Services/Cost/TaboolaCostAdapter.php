<?php

namespace App\Services\Cost;

use App\Contracts\TrafficSourceCostAdapterInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class TaboolaCostAdapter implements TrafficSourceCostAdapterInterface
{
    private const TOKEN_CACHE_KEY = 'tds:taboola:oauth:token';

    public function getSourceKey(): string
    {
        return 'taboola';
    }

    public function fetchSpendByDay(string $fromDate, string $toDate, array $externalCampaignIds): array
    {
        $externalCampaignIds = array_values(array_unique(array_filter(array_map(strval(...), $externalCampaignIds))));
        if ($externalCampaignIds === []) {
            return [];
        }

        if (!$this->isConfigured()) {
            Log::info('taboola.cost_sync skipped: Taboola API credentials not configured');

            return [];
        }

        $out = [];
        foreach ($externalCampaignIds as $campaignId) {
            try {
                $out = array_merge($out, $this->fetchCampaignDayBreakdown($fromDate, $toDate, $campaignId));
            } catch (Throwable $e) {
                Log::error('taboola.cost_sync fetch failed', [
                    'campaign_id' => $campaignId,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        return $out;
    }

    private function isConfigured(): bool
    {
        $cfg = config('tds.taboola', []);

        return ($cfg['account_id'] ?? '') !== ''
            && ($cfg['client_id'] ?? '') !== ''
            && ($cfg['client_secret'] ?? '') !== '';
    }

    /**
     * @return list<CostSpendDayRow>
     */
    private function fetchCampaignDayBreakdown(string $fromDate, string $toDate, string $externalCampaignId): array
    {
        $accountId = (string) config('tds.taboola.account_id');
        $token = $this->accessToken();

        $url = sprintf(
            'https://backstage.taboola.com/backstage/api/1.0/%s/reports/campaign-summary/dimensions/campaign_day_breakdown',
            rawurlencode($accountId),
        );

        $response = Http::timeout(45)
            ->withToken($token)
            ->acceptJson()
            ->get($url, [
                'start_date' => $fromDate,
                'end_date' => $toDate,
                'campaign' => $externalCampaignId,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException(sprintf(
                'Taboola campaign_day_breakdown HTTP %s: %s',
                $response->status(),
                $response->body()
            ));
        }

        $data = $response->json();
        $rows = [];

        foreach ($data['results'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $spent = (float) ($row['spent'] ?? 0);
            $day = $this->normalizeDay($row['date'] ?? null);
            if ($day === null) {
                continue;
            }

            $countryRaw = $row['country'] ?? $row['country_code'] ?? null;
            $country = is_string($countryRaw) && preg_match('/^[A-Za-z]{2}$/', $countryRaw)
                ? strtoupper($countryRaw)
                : null;

            $rows[] = new CostSpendDayRow(
                externalCampaignId: $externalCampaignId,
                day: $day,
                countryCode: $country,
                amount: $spent,
            );
        }

        /** @var array<string, CostSpendDayRow> $merged */
        $merged = [];
        foreach ($rows as $r) {
            $k = $r->externalCampaignId.'|'.$r->day.'|'.($r->countryCode ?? '');
            if (!isset($merged[$k])) {
                $merged[$k] = $r;

                continue;
            }
            $prev = $merged[$k];
            $merged[$k] = new CostSpendDayRow(
                externalCampaignId: $prev->externalCampaignId,
                day: $prev->day,
                countryCode: $prev->countryCode,
                amount: $prev->amount + $r->amount,
            );
        }

        return array_values($merged);
    }

    private function normalizeDay(mixed $date): ?string
    {
        if (!is_string($date) || $date === '') {
            return null;
        }

        try {
            return Carbon::parse($date)->utc()->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function accessToken(): string
    {
        $ttl = max(60, (int) config('tds.taboola.token_cache_ttl_seconds', 3500));

        return Cache::remember(self::TOKEN_CACHE_KEY, $ttl, function (): string {
            $clientId = (string) config('tds.taboola.client_id');
            $clientSecret = (string) config('tds.taboola.client_secret');

            $response = Http::timeout(30)
                ->asForm()
                ->post('https://backstage.taboola.com/backstage/oauth/token', [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'grant_type' => 'client_credentials',
                ]);

            if (!$response->successful()) {
                throw new \RuntimeException(sprintf(
                    'Taboola oauth HTTP %s: %s',
                    $response->status(),
                    $response->body()
                ));
            }

            $token = $response->json('access_token');
            if (!is_string($token) || $token === '') {
                throw new \RuntimeException('Taboola oauth response missing access_token');
            }

            return $token;
        });
    }
}
