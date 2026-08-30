<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6066 (TRAVEL-414) — Consommation de l'outbox TravelAgency.
 *
 * Ajoute l'état `processing` (lease de worker, pattern `crm_outbox_events`
 * #5741) et la colonne `processed_at` pour la traçabilité du dispatch.
 * La contrainte CHECK existante (pending|published|failed) est remplacée
 * pour accepter `processing`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_outbox_events')) {
            return;
        }

        DB::statement('ALTER TABLE travel_outbox_events DROP CONSTRAINT IF EXISTS travel_outbox_events_status_check');
        DB::statement("ALTER TABLE travel_outbox_events ADD CONSTRAINT travel_outbox_events_status_check CHECK (status IN ('pending', 'processing', 'published', 'failed'))");

        if (! Schema::hasColumn('travel_outbox_events', 'processed_at')) {
            Schema::table('travel_outbox_events', function (Blueprint $table): void {
                $table->timestampTz('processed_at')->nullable();
            });
        }

        DB::statement("COMMENT ON COLUMN travel_outbox_events.status IS 'pending|processing|published|failed (dead-letter apres max attempts).'");
        DB::statement("COMMENT ON COLUMN travel_outbox_events.processed_at IS 'Date de consommation effective (dispatch #6066).'");
    }

    public function down(): void
    {
        if (! schemaTableExists('travel_outbox_events')) {
            return;
        }

        DB::statement('ALTER TABLE travel_outbox_events DROP CONSTRAINT IF EXISTS travel_outbox_events_status_check');
        DB::statement("ALTER TABLE travel_outbox_events ADD CONSTRAINT travel_outbox_events_status_check CHECK (status IN ('pending', 'published', 'failed'))");

        if (Schema::hasColumn('travel_outbox_events', 'processed_at')) {
            Schema::table('travel_outbox_events', function (Blueprint $table): void {
                $table->dropColumn('processed_at');
            });
        }
    }
};
