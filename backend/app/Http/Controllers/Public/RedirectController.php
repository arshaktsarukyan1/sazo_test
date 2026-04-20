<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\RedirectCampaignRequest;
use App\Services\Public\PublicRedirectService;
use Illuminate\Http\RedirectResponse;

class RedirectController extends Controller
{
    public function __construct(private readonly PublicRedirectService $publicRedirectService)
    {
    }

    public function handle(RedirectCampaignRequest $request): RedirectResponse
    {
        $campaignSlug = $request->validated('campaignSlug');

        $result = $this->publicRedirectService->resolveRedirect($request, $campaignSlug);

        if ($result === null) {
            abort(404, 'Campaign not found or inactive');
        }

        $response = redirect()->away($result['destination']);

        if ($result['set_session_cookie']) {
            $response = $response->cookie('tds_session_uuid', $result['session_uuid'], 60 * 24 * 30);
        }

        return $response;
    }
}
