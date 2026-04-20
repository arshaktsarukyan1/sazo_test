<?php

namespace App\Services\Public;

use Illuminate\Http\Request;

/**
 * Placeholder risk / bot flags for future fraud pipelines. Safe defaults only.
 *
 * @return array<string, mixed>
 */
final class RiskSignals
{
    public function snapshotForRequest(Request $request): array
    {
        $ua = strtolower((string) $request->userAgent());

        return [
            'bot_placeholder' => str_contains($ua, 'headless') || str_contains($ua, 'phantomjs'),
            'empty_ua_placeholder' => $ua === '',
            'datacenter_asn_placeholder' => false,
            'version' => 1,
        ];
    }
}
