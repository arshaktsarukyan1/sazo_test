<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IngestionIdempotencyKey extends Model
{
    public const SCOPE_SHOPIFY_WEBHOOK = 'shopify_webhook';

    /** Taboola cost sync CLI / worker idempotency scope */
    public const SCOPE_TABOOLA_COST_SYNC = 'taboola_cost_sync';

    protected $fillable = [
        'scope',
        'external_key',
    ];
}
