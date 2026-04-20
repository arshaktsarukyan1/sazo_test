<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Conversion extends Model
{
    protected $fillable = [
        'campaign_id',
        'click_id',
        'source',
        'external_order_id',
        'amount',
        'metadata',
        'note',
        'country_code',
        'device_type',
        'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'converted_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function click(): BelongsTo
    {
        return $this->belongsTo(Click::class);
    }
}
