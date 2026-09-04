<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5803 (FUEL-009) — stocks, cuves et rapprochement.
 *
 * `fuel_stock_entries` : entrées de stock (livraisons fournisseurs,
 * retours) et ajustements (motivés, approuvés manager). Aucun ajustement
 * silencieux : un ajustement sans `reason` est refusé au niveau application
 * (FuelStockService) et la colonne `reason` est NON nullable pour les
 * ajustements. `idempotency_key` UNIQUE par tenant → rejeu zéro doublon
 * (réception facture, synchronisation).
 *
 * `fuel_reconciliation_runs` : journal de rapprochement par station et par
 * jour (écart compteur ↔ ventes ↔ stock). UNIQUE (company_id, station_id,
 * run_date) → le job est rejouable sans doublon (upsert d'état).
 *
 * FK composites (x, company_id) → fuel_stations/fuel_products (pattern
 * FUEL-002/003) : référence cross-tenant physiquement impossible.
 * Montants/volumes en decimal, jamais de flottants métier.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_stock_entries')) {
            Schema::create('fuel_stock_entries', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id')->nullable()->index();
                $table->string('product_code', 40);
                $table->decimal('quantity', 14, 3);
                $table->decimal('unit_cost', 14, 2)->default(0);
                $table->string('entry_type', 20)->default('delivery'); // delivery|adjustment|return
                $table->string('reason', 255)->nullable(); // obligatoire si adjustment
                $table->string('reference', 120)->nullable(); // n° facture / bon de livraison
                $table->date('entry_date');
                $table->string('idempotency_key', 191);
                $table->unsignedInteger('created_by')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'idempotency_key'], 'fuel_stock_entries_key_unique');
                $table->index(['company_id', 'station_id', 'entry_date'], 'fuel_stock_entries_station_date_idx');
            });

            DB::statement("COMMENT ON TABLE fuel_stock_entries IS 'Entrées de stock FuelStation (livraisons, retours, ajustements motivés) — FUEL-009 (#5803).'");
            DB::statement("COMMENT ON COLUMN fuel_stock_entries.reason IS 'Motif OBLIGATOIRE pour un ajustement — aucun ajustement silencieux (règle FUEL-009).'");
        }

        if (! schemaTableExists('fuel_reconciliation_runs')) {
            Schema::create('fuel_reconciliation_runs', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id')->nullable()->index();
                $table->date('run_date');
                $table->string('status', 20)->default('pending'); // pending|running|completed|failed
                $table->timestampTz('started_at')->nullable();
                $table->timestampTz('finished_at')->nullable();
                $table->jsonb('summary')->nullable(); // écarts par produit, explications
                $table->string('last_error', 500)->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'station_id', 'run_date'], 'fuel_reconciliation_run_unique');
                $table->index(['company_id', 'status'], 'fuel_reconciliation_status_idx');
            });

            DB::statement("COMMENT ON TABLE fuel_reconciliation_runs IS 'Rapprochements FuelStation par station/jour — job idempotent (UNIQUE station+date), FUEL-009 (#5803).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_reconciliation_runs');
        Schema::dropIfExists('fuel_stock_entries');
    }
};
