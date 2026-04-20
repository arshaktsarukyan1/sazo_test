<?php

namespace App\Services\Webhooks;

use App\Models\Conversion;
use App\Models\IngestionIdempotencyKey;
use App\Services\Ingestion\IngestionIdempotency;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ShopifyOrderWebhookService
{
    public function __construct(private readonly IngestionIdempotency $ingestionIdempotency)
    {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     status: int,
     *     body: array<string, mixed>
     * }
     */
    public function processOrderWebhook(Request $request, array $payload): array
    {
        $webhookId = trim((string) $request->header('X-Shopify-Webhook-Id', ''));

        if ($webhookId !== '' && $this->ingestionIdempotency->has(IngestionIdempotencyKey::SCOPE_SHOPIFY_WEBHOOK, $webhookId)) {
            return [
                'status' => 200,
                'body' => [
                    'ok' => true,
                    'deduped' => true,
                    'reason' => 'webhook_id',
                ],
            ];
        }

        $orderId = (string) ($payload['id'] ?? $payload['order_id'] ?? '');
        if ($orderId === '') {
            return [
                'status' => 422,
                'body' => ['message' => 'Missing order id'],
            ];
        }

        $amount = (float) ($payload['total_price'] ?? $payload['amount'] ?? 0);
        $click = $this->resolveClick($payload);
        $campaignId = $this->resolveCampaignId($payload, $click);

        if (! $campaignId) {
            return [
                'status' => 422,
                'body' => ['message' => 'Unable to map conversion to campaign'],
            ];
        }

        $convertedAt = $this->parseOrderTimestamp($payload);
        $metadata = $this->buildOrderMetadata($payload);

        $dedupedRace = false;

        DB::transaction(function () use (
            $orderId,
            $amount,
            $click,
            $campaignId,
            $convertedAt,
            $metadata,
            $webhookId,
            &$dedupedRace
        ): void {
            if ($webhookId !== '') {
                if (! $this->ingestionIdempotency->tryAcquire(IngestionIdempotencyKey::SCOPE_SHOPIFY_WEBHOOK, $webhookId)) {
                    $dedupedRace = true;

                    return;
                }
            }

            try {
                Conversion::query()->updateOrCreate(
                    ['external_order_id' => $orderId],
                    [
                        'campaign_id' => $campaignId,
                        'click_id' => $click?->id,
                        'source' => 'shopify',
                        'amount' => $amount,
                        'country_code' => $click?->country_code,
                        'device_type' => $click?->device_type,
                        'converted_at' => $convertedAt,
                        'metadata' => $metadata,
                    ]
                );
            } catch (\Throwable $e) {
                if ($webhookId !== '') {
                    $this->ingestionIdempotency->release(IngestionIdempotencyKey::SCOPE_SHOPIFY_WEBHOOK, $webhookId);
                }

                throw $e;
            }
        });

        if ($dedupedRace) {
            return [
                'status' => 200,
                'body' => [
                    'ok' => true,
                    'deduped' => true,
                    'reason' => 'webhook_id_race',
                ],
            ];
        }

        return [
            'status' => 202,
            'body' => [
                'ok' => true,
                'order_id' => $orderId,
                'campaign_id' => $campaignId,
                'mapped_click' => $click?->click_uuid,
            ],
        ];
    }

    private function resolveClick(array $payload): ?object
    {
        foreach ($this->collectClickIdentifiers($payload) as $id) {
            $id = trim((string) $id);
            if ($id === '') {
                continue;
            }

            $click = DB::table('clicks')->where('click_uuid', $id)->first();
            if ($click) {
                return $click;
            }
        }

        return null;
    }

    /**
     * @return list<string|int|float>
     */
    private function collectClickIdentifiers(array $payload): array
    {
        $out = [];
        foreach (['click_id', 'click_uuid', 'tds_click_id'] as $key) {
            if (! empty($payload[$key])) {
                $out[] = $payload[$key];
            }
        }

        foreach ($payload['note_attributes'] ?? [] as $attr) {
            if (! is_array($attr)) {
                continue;
            }
            $name = strtolower((string) ($attr['name'] ?? ''));
            if (in_array($name, ['click_id', 'click_uuid', 'tds_click_id'], true) && isset($attr['value'])) {
                $out[] = $attr['value'];
            }
        }

        foreach ($payload['line_items'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            foreach ($item['properties'] ?? [] as $prop) {
                if (! is_array($prop) || ! isset($prop['name'], $prop['value'])) {
                    continue;
                }
                $name = strtolower((string) $prop['name']);
                if (in_array($name, ['click_id', 'click_uuid', 'tds_click_id'], true)) {
                    $out[] = $prop['value'];
                }
            }
        }

        return array_values(array_unique($out, SORT_REGULAR));
    }

    private function resolveCampaignId(array $payload, ?object $click): ?int
    {
        if ($click) {
            return (int) $click->campaign_id;
        }

        if (! empty($payload['campaign_id'])) {
            return (int) $payload['campaign_id'];
        }

        if (! empty($payload['campaign']) && is_string($payload['campaign'])) {
            $id = DB::table('campaigns')->where('slug', $payload['campaign'])->value('id');

            return $id ? (int) $id : null;
        }

        return null;
    }

    private function parseOrderTimestamp(array $payload): Carbon
    {
        foreach (['processed_at', 'created_at', 'updated_at'] as $key) {
            if (empty($payload[$key]) || ! is_string($payload[$key])) {
                continue;
            }
            try {
                return Carbon::parse($payload[$key]);
            } catch (\Throwable) {
                continue;
            }
        }

        return Carbon::now();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOrderMetadata(array $payload): array
    {
        $items = [];
        foreach (array_slice($payload['line_items'] ?? [], 0, 100) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $items[] = [
                'id' => $item['id'] ?? null,
                'title' => $item['title'] ?? null,
                'quantity' => $item['quantity'] ?? null,
                'sku' => $item['sku'] ?? null,
                'variant_id' => $item['variant_id'] ?? null,
                'price' => $item['price'] ?? null,
            ];
        }

        return [
            'currency' => $payload['currency'] ?? null,
            'financial_status' => $payload['financial_status'] ?? null,
            'source_name' => $payload['source_name'] ?? null,
            'line_items' => $items,
        ];
    }
}
