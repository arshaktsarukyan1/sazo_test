<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDomainRequest;
use App\Http\Requests\Api\V1\UpdateDomainRequest;
use App\Services\DomainService;
use Illuminate\Http\JsonResponse;

class DomainController extends Controller
{
    public function __construct(private readonly DomainService $domainService)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json($this->domainService->paginateIndex());
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->domainService->findForShow($id)]);
    }

    public function store(StoreDomainRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->domainService->create($request->validated())], 201);
    }

    public function update(UpdateDomainRequest $request, int $id): JsonResponse
    {
        return response()->json(['data' => $this->domainService->update($id, $request->validated())]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->domainService->delete($id);

        return response()->json([], 204);
    }
}
