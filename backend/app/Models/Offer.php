<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Offer extends Model
{
    protected $fillable = [
        'name',
        'url',
    ];

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class, 'campaign_offers')
            ->withPivot(['weight_percent', 'is_active'])
            ->withTimestamps();
    }

    public function targetingRules(): HasMany
    {
        return $this->hasMany(TargetingRule::class);
    }
}
