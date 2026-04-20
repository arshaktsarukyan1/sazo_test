<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Ops\OpsService;
use Illuminate\Http\JsonResponse;

class OpsController extends Controller
{
    public function __construct(private readonly OpsService $opsService)
    {
    }

    public function syncRuns(): JsonResponse
    {
        $payload = $this->opsService->syncRunsWithHeartbeat();

        return response()->json($payload);
    }
}
