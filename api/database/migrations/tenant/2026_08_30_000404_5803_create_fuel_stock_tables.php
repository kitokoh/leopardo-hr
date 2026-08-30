<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5803 (FUEL-009) — stocks, cuves et rapprochement.
 *
 * Trois tables additives, tenant-scoped (`company_id` partout, FK composites
 * anti cross-tenant) :
 *  - `fuel_stock_movements` : journal append-only des mouvements de stock
 *    (livraison +, vente −, ajustement signé) — aucune mise à jour destructive,
 *    `idempotency_key` UNIQUE (company_id, idempotency_key) → zéro doublon au
 *    rejeu ; quantités en unités mineures entières signées (jamais de flottant
 *    métier) ;
 *  - `fuel_deliveries` : livraisons (draft → received → verified), `external_id`
 *    UNIQUE (company_id, external_id) pour le rejeu des imports ;
 *  - `fuel_stock_reconciliations` : snapshots de rapprochement par
 *    (station, produit, jour) — UNIQUE → le job est rejouable (upsert), un
 *    écart n'est jamais silencieux (status variance + notes).
 *
 * Migration additive + idempotente (garde schemaTableExists #1962/#5431).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Prérequis FK composites.
        foreach (['fuel_tanks'] as $table) {
            $this->addIdCompanyUnique($table);
        }

        if (! schemaTableExists('fuel_stock_movements')) {
            Schema::create('fuel_stock_movements', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id')->index();
                $table->unsignedBigInteger('tank_id')->nullable()->index();
                $table->string('product_type', 40);
                // delivery|sale|adjustment|transfer
                $table->string('type', 20);
                // Quantité signée en unités mineures : livraison +, vente −,
                // ajustement ± (le signe porte la direction).
                $table->bigInteger('quantity_minor');
                $table->string('reason', 255)->nullable();
                $table->string('reference', 120)->nullable();
                $table->string('idempotency_key', 160)->nullable();
                $table->unsignedInteger('recorded_by')->nullable();
                $table->timestampTz('recorded_at')->useCurrent();
                $table->timestamps();

                $table->unique(['company_id', 'idempotency_key'], 'fuel_stock_movements_idempotency_unique');
                $table->index(['company_id', 'station_id', 'product_type', 'recorded_at'], 'fuel_stock_movements_company_station_product_time_idx');
                $table->index(['company_id', 'type'], 'fuel_stock_movements_company_type_idx');

                $table->foreign(['station_id', 'company_id'], 'fuel_stock_movements_station_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_stations')
                    ->cascadeOnDelete();
                $table->foreign(['tank_id', 'company_id'], 'fuel_stock_movements_tank_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_tanks')
                    ->cascadeOnDelete();
                $table->foreign('recorded_by', 'fuel_stock_movements_recorded_by_fk')
                    ->references('id')
                    ->on('employees')
                    ->nullOnDelete();
            });
        }

        if (! schemaTableExists('fuel_deliveries')) {
            Schema::create('fuel_deliveries', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id')->index();
                $table->unsignedBigInteger('tank_id')->nullable()->index();
                $table->string('product_type', 40);
                $table->unsignedBigInteger('quantity_minor');
                $table->timestampTz('delivered_at')->useCurrent();
                // manual|supplier|import
                $table->string('source', 20)->default('manual');
                // draft|received|verified
                $table->string('status', 20)->default('received');
                $table->string('external_id', 120)->nullable();
                $table->unsignedInteger('received_by')->nullable();
                $table->timestampTz('received_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'external_id'], 'fuel_deliveries_external_unique');
                $table->index(['company_id', 'station_id', 'delivered_at'], 'fuel_deliveries_company_station_time_idx');

                $table->foreign(['station_id', 'company_id'], 'fuel_deliveries_station_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_stations')
                    ->cascadeOnDelete();
                $table->foreign(['tank_id', 'company_id'], 'fuel_deliveries_tank_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_tanks')
                    ->cascadeOnDelete();
                $table->foreign('received_by', 'fuel_deliveries_received_by_fk')
                    ->references('id')
                    ->on('employees')
                    ->nullOnDelete();
            });
        }

        if (! schemaTableExists('fuel_stock_reconciliations')) {
            Schema::create('fuel_stock_reconciliations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id')->index();
                $table->string('product_type', 40);
                $table->date('day');
                $table->bigInteger('opening_minor')->default(0);
                $table->bigInteger('deliveries_minor')->default(0);
                $table->bigInteger('sales_minor')->default(0);
                $table->bigInteger('adjustments_minor')->default(0);
                $table->bigInteger('expected_closing_minor')->default(0);
                // Delta compté par les compteurs (optionnel selon équipement).
                $table->bigInteger('metered_delta_minor')->nullable();
                $table->bigInteger('variance_minor')->nullable();
                // balanced|variance
                $table->string('status', 20)->default('balanced');
                $table->string('notes', 500)->nullable();
                $table->timestampTz('computed_at')->useCurrent();
                $table->timestamps();

                $table->unique(
                    ['company_id', 'station_id', 'product_type', 'day'],
                    'fuel_stock_reconciliations_company_station_product_day_unique'
                );
                $table->index(['company_id', 'station_id', 'day'], 'fuel_stock_reconciliations_company_station_day_idx');

                $table->foreign(['station_id', 'company_id'], 'fuel_stock_reconciliations_station_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_stations')
                    ->cascadeOnDelete();
            });
        }

        $this->addChecks();
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_stock_reconciliations');
        Schema::dropIfExists('fuel_deliveries');
        Schema::dropIfExists('fuel_stock_movements');
    }

    private function addIdCompanyUnique(string $table): void
    {
        if (! schemaTableExists($table)) {
            return;
        }

        $constraint = $table.'_id_company_unique';

        $row = DB::selectOne('SELECT 1 FROM pg_constraint WHERE conname = ?', [$constraint]);

        if ($row !== null) {
            return;
        }

        $schema = resolveTableSchema($table);

        if ($schema !== null) {
            DB::statement(
                "ALTER TABLE {$schema}.{$table} ADD CONSTRAINT {$constraint} UNIQUE (id, company_id)"
            );
        }
    }

    private function constraintExists(string $name): bool
    {
        $row = DB::selectOne('SELECT 1 FROM pg_constraint WHERE conname = ?', [$name]);

        return $row !== null;
    }

    private function addChecks(): void
    {
        $schema = resolveTableSchema('fuel_stock_movements');

        if ($schema !== null && ! $this->constraintExists('fuel_stock_movements_type_check')) {
            DB::statement(
                "ALTER TABLE {$schema}.fuel_stock_movements ADD CONSTRAINT fuel_stock_movements_type_check CHECK (type IN ('delivery', 'sale', 'adjustment', 'transfer'))"
            );
        }

        $schemaDeliveries = resolveTableSchema('fuel_deliveries');

        if ($schemaDeliveries !== null && ! $this->constraintExists('fuel_deliveries_source_check')) {
            DB::statement(
                "ALTER TABLE {$schemaDeliveries}.fuel_deliveries ADD CONSTRAINT fuel_deliveries_source_check CHECK (source IN ('manual', 'supplier', 'import'))"
            );
        }

        if ($schemaDeliveries !== null && ! $this->constraintExists('fuel_deliveries_status_check')) {
            DB::statement(
                "ALTER TABLE {$schemaDeliveries}.fuel_deliveries ADD CONSTRAINT fuel_deliveries_status_check CHECK (status IN ('draft', 'received', 'verified'))"
            );
        }
    }
};
