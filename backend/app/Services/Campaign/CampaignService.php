<?php

namespace App\Services\Campaign;

use App\Models\Campaign;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class CampaignService
{
    public function paginateIndex(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return Campaign::query()
            ->with(['domain', 'trafficSource'])
            ->latest('id')
            ->paginate(20);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): Campaign
    {
        return DB::transaction(function () use ($payload): Campaign {
            $campaign = Campaign::query()->create(Arr::except($payload, ['landers', 'offers']));
            $this->syncSplits($campaign, $payload);

            return $campaign->load(['landers', 'offers', 'trafficSource', 'domain']);
        });
    }

    public function findForShow(int $id): Campaign
    {
        return Campaign::query()
            ->with(['landers', 'offers', 'trafficSource', 'domain', 'targetingRules'])
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(int $id, array $payload): Campaign
    {
        $campaign = Campaign::query()->findOrFail($id);

        return DB::transaction(function () use ($campaign, $payload): Campaign {
            $campaign->fill(Arr::except($payload, ['landers', 'offers']));
            $campaign->save();
            $this->syncSplits($campaign, $payload);

            return $campaign->load(['landers', 'offers', 'trafficSource', 'domain']);
        });
    }

    public function setStatus(int $id, string $status): Campaign
    {
        $campaign = Campaign::query()->findOrFail($id);
        $campaign->status = $status;
        $campaign->save();

        return $campaign;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncSplits(Campaign $campaign, array $payload): void
    {
        if (array_key_exists('landers', $payload)) {
            $rows = $payload['landers'];
            $campaign->landers()->sync(count($rows) ? $this->buildPivotMap($rows) : []);
        }

        if (array_key_exists('offers', $payload)) {
            $rows = $payload['offers'];
            $campaign->offers()->sync(count($rows) ? $this->buildPivotMap($rows) : []);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<int, array{weight_percent: int, is_active: bool}>
     */
    private function buildPivotMap(array $rows): array
    {
        $mapped = [];

        foreach ($rows as $row) {
            $mapped[(int) $row['id']] = [
                'weight_percent' => (int) $row['weight_percent'],
                'is_active' => (bool) ($row['is_active'] ?? true),
            ];
        }

        return $mapped;
    }
}
