<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8358 (VTC-02) — BC-34 VTC : table tenant `vtc_ride_events`.
 *
 * Journal APPEND-ONLY du cycle de vie d'une course : chaque transition de
 * la state machine (VTC-04) et chaque offre du dispatch y écrit une ligne
 * immuable (`created_at` seul, jamais d'update) — audit complet « qui a
 * fait quoi, quand » (offres, timeouts, acceptations, annulations avec
 * motif). Tenant-first, aucune FK, réentrante + down() complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('vtc_ride_events')) {
            Schema::create('vtc_ride_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('ride_id');
                $table->string('type', 60);
                $table->json('payload')->nullable();

                $table->timestamp('created_at')->useCurrent();

                $table->index(['company_id', 'ride_id', 'created_at'], 'vtc_ride_events_company_ride_at_idx');
            });

            DB::statement("COMMENT ON TABLE vtc_ride_events IS 'Journal append-only des courses VTC - transitions state machine et offres dispatch, immuable (VTC-02/#8358).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vtc_ride_events');
    }
};
