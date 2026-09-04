<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #6066 (TRAVEL-414) — extension de l'outbox TravelAgency pour la
 * consommation asynchrone (pattern CrmOutboxDispatchCommand #5741).
 *
 * La table créée en TRAVEL-211 (#6024) ne porte que pending/published/failed
 * et pas de `processed_at` : sans état intermédiaire `processing`, deux
 * workers peuvent traiter le même événement, et un crash worker perd
 * silencieusement un événement déjà claimé. Cette migration additive ajoute :
 *  - `processed_at` (timestampTz nullable) ;
 *  - l'état `processing` au CHECK (claim atomique + lease de 15 min, reprise
 *    des orphelins — exactement le contrat `crm_outbox_events` #5741).
 *
 * Réentrante : gardes schemaHasColumn + pg_constraint (#1613).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('travel_outbox_events') && ! schemaHasColumn('travel_outbox_events', 'processed_at')) {
            Schema::table('travel_outbox_events', function (Blueprint $table): void {
                $table->timestampTz('processed_at')->nullable();
            });
        }

        $schema = resolveTableSchema('travel_outbox_events');
        if ($schema === null) {
            return;
        }

        DB::statement(
            'DO $$ BEGIN '
            ."IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'travel_outbox_events_status_check' "
            ."AND NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'travel_outbox_events_status_check_v2')) THEN "
            ."ALTER TABLE \"{$schema}\".\"travel_outbox_events\" DROP CONSTRAINT travel_outbox_events_status_check; "
            .'END IF; '
            ."IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'travel_outbox_events_status_check_v2') THEN "
            ."ALTER TABLE \"{$schema}\".\"travel_outbox_events\" ADD CONSTRAINT travel_outbox_events_status_check_v2 "
            ."CHECK (status IN ('pending','processing','published','failed')); "
            .'END IF; END $$'
        );
    }

    public function down(): void
    {
        if (schemaTableExists('travel_outbox_events') && schemaHasColumn('travel_outbox_events', 'processed_at')) {
            Schema::table('travel_outbox_events', function (Blueprint $table): void {
                $table->dropColumn('processed_at');
            });
        }
    }
};
