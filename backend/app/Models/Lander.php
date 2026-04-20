<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Lander extends Model
{
    protected $fillable = [
        'name',
        'url',
    ];

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class, 'campaign_landers')
            ->withPivot(['weight_percent', 'is_active'])
            ->withTimestamps();
    }
}
