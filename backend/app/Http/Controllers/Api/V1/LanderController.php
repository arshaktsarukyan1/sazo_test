<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreLanderRequest;
use App\Http\Requests\Api\V1\UpdateLanderRequest;
use App\Services\LanderService;
use Illuminate\Http\JsonResponse;

class LanderController extends Controller
{
    public function __construct(private readonly LanderService $landerService)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json($this->landerService->paginateIndex());
    }

    public function store(StoreLanderRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->landerService->create($request->validated())], 201);
    }

    public function update(UpdateLanderRequest $request, int $id): JsonResponse
    {
        return response()->json(['data' => $this->landerService->update($id, $request->validated())]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->landerService->delete($id);

        return response()->json([], 204);
    }
}
