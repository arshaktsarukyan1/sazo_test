<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ingestion_idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 64);
            $table->string('external_key', 255);
            $table->timestamps();

            $table->unique(['scope', 'external_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingestion_idempotency_keys');
    }
};
