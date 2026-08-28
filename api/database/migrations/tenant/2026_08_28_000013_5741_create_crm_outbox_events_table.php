<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #5741 — Outbox des événements CRM (tenant-scoped).
 *
 * Fiabilité des files CRM : un effet métier (notification, export, callback
 * provider, relance…) est d'abord PERSISTÉ dans cette table APRÈS le commit
 * de la transaction métier, puis consommé de façon asynchrone et idempotente
 * par `crm:outbox-dispatch`. Un crash entre le commit et la consommation ne
 * perd rien (replay) ; la contrainte unique (company_id, idempotency_key)
 * garantit zéro doublon même en cas de rejeu.
 *
 * `company_id` non nullable ; statuts pending/processing/sent/failed ;
 * `available_at` porte le backoff (transitoire) ; attempts borne le dead-letter.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('crm_outbox_events')) {
            Schema::create('crm_outbox_events', function (Blueprint $table) {
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
                $table->unique(['company_id', 'idempotency_key'], 'crm_outbox_company_key_unique');
                $table->index(['company_id', 'status', 'available_at'], 'crm_outbox_company_status_due_idx');
            });

            DB::statement("COMMENT ON TABLE crm_outbox_events IS 'Outbox des événements CRM : persistance après commit, consommation asynchrone idempotente, replay sans perte ni doublon (#5741).'");
            DB::statement("COMMENT ON COLUMN crm_outbox_events.status IS 'pending|processing|sent|failed (dead-letter après max attempts).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_outbox_events');
    }
};
