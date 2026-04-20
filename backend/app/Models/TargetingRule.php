<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TargetingRule extends Model
{
    protected $fillable = [
        'campaign_id',
        'offer_id',
        'country_code',
        'region',
        'device_type',
        'priority',
        'is_active',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}
