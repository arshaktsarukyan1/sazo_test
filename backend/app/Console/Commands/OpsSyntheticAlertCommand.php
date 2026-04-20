<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class OpsSyntheticAlertCommand extends Command
{
    protected $signature = 'tds:ops-synthetic-alert';

    protected $description = 'Emit a deterministic test log line for ops alerting / Log::fake assertions';

    public function handle(): int
    {
        Log::channel('alerts')->critical('synthetic_ops_alert_test', [
            'source' => 'tds:ops-synthetic-alert',
            'ts' => now()->toIso8601String(),
        ]);

        return self::SUCCESS;
    }
}
