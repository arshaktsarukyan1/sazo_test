<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('conversions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('click_id')->nullable()->constrained('clicks')->nullOnDelete();
            $table->string('source')->default('manual');
            $table->string('external_order_id')->nullable()->unique();
            $table->decimal('amount', 12, 2);
            $table->json('metadata')->nullable();
            $table->text('note')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->enum('device_type', ['desktop', 'mobile', 'tablet'])->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'created_at']);
            $table->index(['campaign_id', 'country_code', 'device_type', 'created_at'], 'conversions_campaign_geo_device_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversions');
    }
};
