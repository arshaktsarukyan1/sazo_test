<?php

namespace App\Services\Public;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Resolves a public campaign row for /campaign/{slug} (and legacy /r/{slug}) and /click using optional domain binding.
 * When a campaign has domain_id, the request Host must match domains.name (case-insensitive)
 * and the domain must be status=active with is_active=true.
 */
final class CampaignPublicLookup
{
    /**
     * @param  list<string>  $statuses  Allowed campaign.status values (e.g. ['active'] or ['active','paused']).
     */
    public function bySlugAndHost(string $slug, string $host, array $statuses): ?object
    {
        $hostNorm = strtolower($host);
        $ttl = (int) config('tds.redirect_cache_ttl_seconds', 60);
        $statusKey = implode(',', $statuses);
        $cacheKey = sprintf('tds:public-campaign:%s:%s:%s', $slug, $hostNorm, $statusKey);

        return Cache::remember($cacheKey, $ttl, function () use ($slug, $hostNorm, $statuses): ?object {
            $row = DB::table('campaigns')
                ->leftJoin('domains', 'domains.id', '=', 'campaigns.domain_id')
                ->where('campaigns.slug', $slug)
                ->whereIn('campaigns.status', $statuses)
                ->select(
                    'campaigns.*',
                    'domains.name as domain_name',
                    'domains.status as domain_status',
                    'domains.is_active as domain_is_active',
                )
                ->first();

            if (!$row) {
                return null;
            }

            if ($row->domain_id !== null) {
                $expected = $row->domain_name !== null ? strtolower((string) $row->domain_name) : '';
                if ($expected === '' || $expected !== $hostNorm) {
                    return null;
                }
                if (($row->domain_status ?? '') !== 'active' || !(bool) $row->domain_is_active) {
                    return null;
                }
            }

            return $row;
        });
    }
}
