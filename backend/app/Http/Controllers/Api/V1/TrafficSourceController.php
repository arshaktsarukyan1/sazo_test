<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\TrafficSourceService;
use Illuminate\Http\JsonResponse;

class TrafficSourceController extends Controller
{
    public function __construct(private readonly TrafficSourceService $trafficSourceService)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->trafficSourceService->listActive()]);
    }
}
