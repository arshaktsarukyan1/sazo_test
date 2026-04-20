<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCampaignRequest;
use App\Http\Requests\Api\V1\UpdateCampaignRequest;
use App\Services\Campaign\CampaignService;
use Illuminate\Http\JsonResponse;

class CampaignController extends Controller
{
    public function __construct(private readonly CampaignService $campaignService)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json($this->campaignService->paginateIndex());
    }

    public function store(StoreCampaignRequest $request): JsonResponse
    {
        $campaign = $this->campaignService->create($request->validated());

        return response()->json(['data' => $campaign], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->campaignService->findForShow($id)]);
    }

    public function update(UpdateCampaignRequest $request, int $id): JsonResponse
    {
        $campaign = $this->campaignService->update($id, $request->validated());

        return response()->json(['data' => $campaign]);
    }

    public function activate(int $id): JsonResponse
    {
        return response()->json(['data' => $this->campaignService->setStatus($id, 'active')]);
    }

    public function pause(int $id): JsonResponse
    {
        return response()->json(['data' => $this->campaignService->setStatus($id, 'paused')]);
    }

    public function archive(int $id): JsonResponse
    {
        return response()->json(['data' => $this->campaignService->setStatus($id, 'archived')]);
    }
}
