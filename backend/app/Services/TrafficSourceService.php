<?php

namespace App\Services;

use App\Models\TrafficSource;
use Illuminate\Database\Eloquent\Collection;

final class TrafficSourceService
{
    public function listActive(): Collection
    {
        return TrafficSource::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
