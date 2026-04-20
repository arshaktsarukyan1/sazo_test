<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Visit extends Model
{
    protected $fillable = [
        'campaign_id',
        'session_id',
        'lander_id',
        'country_code',
        'device_type',
        'risk_flags',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'risk_flags' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function lander(): BelongsTo
    {
        return $this->belongsTo(Lander::class);
    }
}
