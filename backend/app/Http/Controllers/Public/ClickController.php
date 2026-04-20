<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\PublicClickRequest;
use App\Services\Public\PublicClickService;
use Illuminate\Http\RedirectResponse;

class ClickController extends Controller
{
    public function __construct(private readonly PublicClickService $publicClickService)
    {
    }

    public function handle(PublicClickRequest $request): RedirectResponse
    {
        $campaignSlug = (string) $request->query('campaign');
        $sessionUuid = (string) $request->query('sid', $request->cookie('tds_session_uuid', ''));

        $result = $this->publicClickService->resolveDestination($request, $campaignSlug, $sessionUuid);

        if ($result === null) {
            abort(404, 'Campaign not found');
        }

        return redirect()->away($result['destination']);
    }
}
