<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6024 (TRAVEL-211) — Outbox des événements TravelAgency (tenant-scoped).
 *
 * Structure identique au pattern `crm_outbox_events` (#5741) : un effet
 * métier est d'abord PERSISTÉ dans cette table APRÈS le commit de la
 * transaction métier, puis consommé de façon asynchrone et idempotente. La
 * contrainte unique (company_id, idempotency_key) garantit zéro doublon
 * même en cas de rejeu. `payload_redacted` : jamais de secret/token/PII en
 * clair dans le payload persisté.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('travel_outbox_events')) {
            return;
        }

        Schema::create('travel_outbox_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();

            $table->string('event_type', 80);
            $table->jsonb('payload_redacted');

            // pending → published | failed (dead-letter après max attempts).
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('available_at')->useCurrent();
            $table->text('last_error')->nullable();

            $table->string('idempotency_key', 255);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->unique(['company_id', 'idempotency_key'], 'travel_outbox_company_key_unique');
            $table->index(['company_id', 'status', 'available_at'], 'travel_outbox_company_status_due_idx');
        });

        DB::statement("ALTER TABLE travel_outbox_events ADD CONSTRAINT travel_outbox_events_status_check CHECK (status IN ('pending', 'published', 'failed'))");
        DB::statement("COMMENT ON TABLE travel_outbox_events IS 'Outbox des evenements TravelAgency — pattern identique crm_outbox_events #5741 (TRAVEL-211/#6024).'");
        DB::statement("COMMENT ON COLUMN travel_outbox_events.status IS 'pending|published|failed (dead-letter apres max attempts).'");
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_outbox_events');
    }
};
