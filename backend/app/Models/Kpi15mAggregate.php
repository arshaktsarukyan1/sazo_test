<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Kpi15mAggregate extends Model
{
    protected $table = 'kpi_15m_aggregates';

    protected $fillable = [
        'campaign_id',
        'country_code',
        'device_type',
        'bucket_start',
        'visits',
        'clicks',
        'conversions',
        'revenue',
        'cost',
    ];

    protected function casts(): array
    {
        return [
            'bucket_start' => 'datetime',
            'revenue' => 'decimal:2',
            'cost' => 'decimal:2',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
