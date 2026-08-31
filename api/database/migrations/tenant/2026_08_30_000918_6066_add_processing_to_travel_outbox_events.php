<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * #6066 (TRAVEL-414) — Extension de l'outbox TravelAgency pour la
 * consommation asynchrone (pattern crm_outbox #5741).
 *
 * Ajout du statut `processing` (claim atomique par le worker de dispatch)
 * et de `processed_at` (horodatage de publication effective) — le pattern
 * miroir `CrmOutboxDispatchCommand` en a besoin pour garantir zéro double
 * traitement (lease) et une traçabilité de publication. Additive et
 * réentrante (IF EXISTS / IF NOT EXISTS), aucun renommage.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('travel_outbox_events')) {
            // Statut `processing` : verrou de claim posé par le worker.
            DB::statement('ALTER TABLE travel_outbox_events DROP CONSTRAINT IF EXISTS travel_outbox_events_status_check');
            DB::statement("ALTER TABLE travel_outbox_events ADD CONSTRAINT travel_outbox_events_status_check CHECK (status IN ('pending', 'processing', 'published', 'failed'))");
            DB::statement('ALTER TABLE travel_outbox_events ADD COLUMN IF NOT EXISTS processed_at timestamp(0) with time zone NULL');
            DB::statement("COMMENT ON COLUMN travel_outbox_events.status IS 'pending|processing|published|failed (dead-letter apres max attempts).'");
            DB::statement("COMMENT ON COLUMN travel_outbox_events.processed_at IS 'Horodatage de publication effective (TRAVEL-414/#6066).'");
        }
    }

    public function down(): void
    {
        if (schemaTableExists('travel_outbox_events')) {
            DB::statement('ALTER TABLE travel_outbox_events DROP COLUMN IF EXISTS processed_at');
            DB::statement('ALTER TABLE travel_outbox_events DROP CONSTRAINT IF EXISTS travel_outbox_events_status_check');
            DB::statement("ALTER TABLE travel_outbox_events ADD CONSTRAINT travel_outbox_events_status_check CHECK (status IN ('pending', 'published', 'failed'))");
        }
    }
};
