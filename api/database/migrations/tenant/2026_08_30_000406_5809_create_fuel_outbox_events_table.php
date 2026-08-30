<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5809 (FUEL-015) — outbox des événements FuelStation vers
 * Accounting (et notifications, FUEL-019).
 *
 * Même pattern que `crm_outbox_events` (#5741) : un effet métier est
 * PERSISTÉ après le commit de la transaction métier, puis consommé de façon
 * asynchrone et idempotente par `fuel:outbox-dispatch`. Replay sans perte ;
 * UNIQUE (company_id, idempotency_key) → zéro doublon.
 *
 * Événements versionnés (contrat `docs/contracts/fuel-accounting.md`) :
 * fuel.sale.recorded.v1, fuel.cash_session.closed.v1, fuel.stock.reconciled.v1,
 * fuel.incident.reported.v1, fuel.stock.threshold.breached.v1,
 * fuel.customer.created.v1, fuel.customer.consent.updated.v1,
 * fuel.sync.readings.received.v1, fuel.sync.sales.received.v1.
 *
 * Aucun accès direct d'Accounting aux tables FuelStation : le contrat
 * s'échange par événements versionnés (agrégats validés, sans PII).
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
                $table->timestampTz('available_at', 6)->useCurrent();
                $table->text('last_error')->nullable();
                $table->timestampTz('processed_at')->nullable();

                $table->string('idempotency_key', 255);
                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();

                $table->unique(['company_id', 'idempotency_key'], 'fuel_outbox_company_key_unique');
                $table->index(['company_id', 'status', 'available_at'], 'fuel_outbox_company_status_due_idx');
            });

            DB::statement("COMMENT ON TABLE fuel_outbox_events IS 'Outbox des événements FuelStation (Accounting, notifications) — persistance après commit, consommation asynchrone idempotente (FUEL-015/019).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_outbox_events');
    }
};
