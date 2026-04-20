<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Session extends Model
{
    protected $fillable = [
        'session_uuid',
        'ip',
        'country_code',
        'region',
        'city',
        'device_type',
        'browser',
        'os',
        'language',
        'referrer',
    ];

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(Click::class);
    }
}
