<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5832 (EDU-016) — outbox des événements EduManager (événements
 * versionnés, consommation asynchrone idempotente).
 *
 * Pattern `crm_outbox_events` (#5741) : un effet métier (facturation,
 * encaissement, abandon, relance admission, désinscription marketing) est
 * d'abord PERSISTÉ dans cette table APRÈS le commit de la transaction métier,
 * puis consommé de façon asynchrone et idempotente par
 * `edu:outbox-dispatch`. Un crash entre le commit et la consommation ne perd
 * rien (replay) ; la contrainte unique (company_id, idempotency_key) garantit
 * zéro doublon même en cas de rejeu.
 *
 * `company_id` non nullable ; statuts pending/processing/sent/failed ;
 * `available_at` porte le backoff (transitoire) ; attempts borne le
 * dead-letter. Les event_type sont versionnés (`edu.fee.charge.created.v1`,
 * `edu.fee.payment.recorded.v1`, `edu.admission.followup.v1`, …).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('edu_outbox_events')) {
            Schema::create('edu_outbox_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('event_type', 80);
                $table->string('aggregate_type', 120)->nullable();
                $table->string('aggregate_id', 120)->nullable();
                $table->jsonb('payload');

                // pending → processing → sent | failed (dead après max attempts).
                $table->string('status', 20)->default('pending');
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->timestampTz('available_at')->useCurrent();
                $table->text('last_error')->nullable();
                $table->timestampTz('processed_at')->nullable();

                $table->string('idempotency_key', 255);
                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();

                // Idempotence : une clé (événement, payload) unique par tenant.
                $table->unique(['company_id', 'idempotency_key'], 'edu_outbox_company_key_unique');
                $table->index(['company_id', 'status', 'available_at'], 'edu_outbox_company_status_due_idx');
            });

            $schema = resolveTableSchema('edu_outbox_events');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_outbox_events_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_outbox_events\" ADD CONSTRAINT edu_outbox_events_status_check "
                    ."CHECK (status IN ('pending','processing','sent','failed')); END IF; END $$"
                );
                DB::statement("COMMENT ON TABLE edu_outbox_events IS 'Outbox des événements EduManager : persistance après commit, consommation asynchrone idempotente, replay sans perte ni doublon (#5832).'");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_outbox_events');
    }
};
