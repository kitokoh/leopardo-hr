<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5809 (FUEL-015) — outbox des événements FuelStation (contrat
 * Accounting).
 *
 * Fiabilité du contrat Accounting : un effet de synthèse (agrégats validés
 * de ventes, caisse, stock et écarts) est d'abord PERSISTÉ dans cette table
 * APRÈS le commit de la transaction métier, puis consommé de façon
 * asynchrone et idempotente par `fuel:outbox-dispatch`. Un crash entre le
 * commit et la consommation ne perd rien (replay) ; la contrainte unique
 * (company_id, idempotency_key) garantit zéro doublon même en cas de rejeu.
 *
 * Événements versionnés (spec §7) : `fuel.cash.closed.v1` (émetteur
 * FuelStation → Accounting, audit), `fuel.shift.closed.v1` (→ Attendance,
 * Payroll, Accounting). Un consommateur vérifie version, tenant, correlation
 * ID et clé d'idempotence. L'échec d'un consommateur est isolé du flux
 * opérationnel (retry borné → dead-letter, jamais de blocage des écritures).
 *
 * `company_id` non nullable ; statuts pending/processing/sent/failed ;
 * `available_at` porte le backoff (transitoire) ; attempts borne le
 * dead-letter. Migration additive + idempotente (garde #1962/#5431).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_outbox_events')) {
            Schema::create('fuel_outbox_events', function (Blueprint $table): void {
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
                $table->unique(['company_id', 'idempotency_key'], 'fuel_outbox_company_key_unique');
                $table->index(['company_id', 'status', 'available_at'], 'fuel_outbox_company_status_due_idx');
            });

            DB::statement("COMMENT ON TABLE fuel_outbox_events IS 'Outbox des événements FuelStation (contrat Accounting) : persistance après commit, consommation asynchrone idempotente, replay sans perte ni doublon (#5809).'");
            DB::statement("COMMENT ON COLUMN fuel_outbox_events.status IS 'pending|processing|sent|failed (dead-letter après max attempts).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_outbox_events');
    }
};
