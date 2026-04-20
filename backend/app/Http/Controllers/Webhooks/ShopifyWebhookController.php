<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Webhooks\ShopifyWebhookOrdersRequest;
use App\Services\Webhooks\ShopifyOrderWebhookService;
use Illuminate\Http\JsonResponse;

class ShopifyWebhookController extends Controller
{
    public function __construct(private readonly ShopifyOrderWebhookService $shopifyOrderWebhookService)
    {
    }

    public function orders(ShopifyWebhookOrdersRequest $request): JsonResponse
    {
        $result = $this->shopifyOrderWebhookService->processOrderWebhook($request, $request->validated());

        return response()->json($result['body'], $result['status']);
    }
}
