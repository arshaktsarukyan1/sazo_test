<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cost_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->string('source')->default('taboola');
            $table->string('external_campaign_id')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->decimal('amount', 12, 2);
            $table->json('metadata')->nullable();
            $table->timestamp('bucket_start');
            $table->timestamps();

            $table->unique(['campaign_id', 'source', 'country_code', 'bucket_start'], 'cost_entries_uniq');
            $table->index(['campaign_id', 'bucket_start']);
        });

        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement("COMMENT ON TABLE cost_entries IS 'Append-heavy cost table. Candidate for partitioning by bucket_start.'");
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE cost_entries COMMENT = 'Append-heavy cost table. Candidate for partitioning by bucket_start.'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_entries');
    }
};
