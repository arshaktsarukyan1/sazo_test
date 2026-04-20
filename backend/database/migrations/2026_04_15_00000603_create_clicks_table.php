<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('clicks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('click_uuid')->unique();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('sessions')->cascadeOnDelete();
            $table->foreignId('offer_id')->nullable()->constrained('offers')->nullOnDelete();
            $table->string('country_code', 2)->nullable();
            $table->enum('device_type', ['desktop', 'mobile', 'tablet'])->nullable();
            $table->json('risk_flags')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'created_at']);
            $table->index(['campaign_id', 'country_code', 'device_type', 'created_at'], 'clicks_campaign_geo_device_created_idx');
        });

        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement("COMMENT ON TABLE clicks IS 'High-volume event table. Candidate for monthly range partitioning by created_at.'");
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE clicks COMMENT = 'High-volume event table. Candidate for monthly range partitioning by created_at.'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('clicks');
    }
};
