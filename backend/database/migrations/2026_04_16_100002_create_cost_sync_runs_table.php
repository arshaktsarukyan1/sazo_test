<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cost_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 32);
            $table->string('status', 16);
            $table->timestamp('window_from');
            $table->timestamp('window_to');
            $table->unsignedInteger('rows_upserted')->default(0);
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['source', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_sync_runs');
    }
};
