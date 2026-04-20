<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kpi_15m_aggregates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->string('country_code', 2)->nullable();
            $table->enum('device_type', ['desktop', 'mobile', 'tablet'])->nullable();
            $table->timestamp('bucket_start');
            $table->unsignedBigInteger('visits')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('conversions')->default(0);
            $table->decimal('revenue', 12, 2)->default(0);
            $table->decimal('cost', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['campaign_id', 'country_code', 'device_type', 'bucket_start'], 'kpi15_uniq');
        });

        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement("COMMENT ON TABLE kpi_15m_aggregates IS 'Pre-aggregated table for near real-time dashboard reads.'");
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE kpi_15m_aggregates COMMENT = 'Pre-aggregated table for near real-time dashboard reads.'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_15m_aggregates');
    }
};
