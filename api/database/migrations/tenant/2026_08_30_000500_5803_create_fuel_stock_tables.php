<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5803 (FUEL-009) — stocks, cuves et rapprochement.
 *
 * Trois tables tenant-scoped :
 *  - `fuel_tank_stock_levels` : niveaux de stock observés par cuve et par
 *    jour (source manual|delivery|calculated) — la vérité physique. Rejeu
 *    idempotent via `idempotency_key` (UNIQUE par tenant). Un niveau ne
 *    peut jamais être négatif (CHECK).
 *  - `fuel_stock_deliveries` : livraisons de carburant (entrées en stock) —
 *    référence fournisseur UNIQUE par tenant (rejeu sûr), cycle de vie
 *    draft → received|rejected, réception idempotente.
 *  - `fuel_reconciliation_reports` : rapport d'écart PAR STATION ET PAR
 *    JOUR (UNIQUE (company_id, station_id, report_date)) : le job de
 *    rapprochement est REJOUABLE — relancer recalcule et remplace le
 *    rapport du même jour sans doublon. expected_stock = niveau d'ouverture
 *    + livraisons reçues − ventes (volumes en unités mineures, centilitres).
 *    variance = stock de clôture − stock attendu ; tout écart est EXPLICABLE
 *    (statut pending_review jusqu'à la revue manager qui saisit une
 *    explication) — aucun ajustement silencieux.
 *
 * FKs composites anti cross-tenant (pattern FUEL-002/003) : impossible de
 * rattacher un niveau/livraison/rapport à la cuve/station d'un AUTRE tenant.
 *
 * Migration additive + idempotente (garde schemaTableExists #1962/#5431),
 * clés primaires bigint, company_id uuid indexé, CHECKs gardés pg_constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addIdCompanyUnique('fuel_tanks');
        $this->addIdCompanyUnique('fuel_stations');

        if (! schemaTableExists('fuel_tank_stock_levels')) {
            Schema::create('fuel_tank_stock_levels', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('tank_id');
                $table->date('recorded_on');
                $table->unsignedBigInteger('level_minor');
                // manual | delivery | calculated
                $table->string('source_code', 20)->default('manual');
                $table->string('idempotency_key', 120)->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('recorded_by')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'tank_id', 'recorded_on'], 'fuel_stock_levels_company_tank_date_unique');
                $table->unique(['company_id', 'idempotency_key'], 'fuel_stock_levels_idempotency_unique');
                $table->index(['company_id', 'recorded_on'], 'fuel_stock_levels_company_date_idx');

                $table->foreign(['tank_id', 'company_id'], 'fuel_stock_levels_tank_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_tanks')
                    ->cascadeOnDelete();
            });

            $schema = resolveTableSchema('fuel_tank_stock_levels');

            if ($schema !== null && ! $this->constraintExists('fuel_stock_levels_level_check')) {
                DB::statement(
                    "ALTER TABLE {$schema}.fuel_tank_stock_levels ADD CONSTRAINT fuel_stock_levels_level_check CHECK (level_minor >= 0)"
                );
            }

            if ($schema !== null && ! $this->constraintExists('fuel_stock_levels_source_check')) {
                DB::statement(
                    "ALTER TABLE {$schema}.fuel_tank_stock_levels ADD CONSTRAINT fuel_stock_levels_source_check CHECK (source_code IN ('manual', 'delivery', 'calculated'))"
                );
            }
        }

        if (! schemaTableExists('fuel_stock_deliveries')) {
            Schema::create('fuel_stock_deliveries', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id');
                $table->unsignedBigInteger('tank_id')->nullable();
                $table->string('product_code', 40);
                $table->string('supplier_name', 150)->nullable();
                $table->unsignedBigInteger('quantity_minor');
                $table->string('unit_code', 20)->default('l');
                $table->timestampTz('delivered_at')->useCurrent();
                // Référence fournisseur — UNIQUE par tenant (rejeu idempotent).
                $table->string('reference', 80);
                // draft | received | rejected
                $table->string('status', 20)->default('draft');
                $table->unsignedInteger('received_by')->nullable();
                $table->timestampTz('received_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'reference'], 'fuel_stock_deliveries_reference_unique');
                $table->index(['company_id', 'status'], 'fuel_stock_deliveries_company_status_idx');
                $table->index(['company_id', 'delivered_at'], 'fuel_stock_deliveries_company_date_idx');

                $table->foreign(['station_id', 'company_id'], 'fuel_stock_deliveries_station_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_stations')
                    ->cascadeOnDelete();
                $table->foreign(['tank_id', 'company_id'], 'fuel_stock_deliveries_tank_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_tanks')
                    ->nullOnDelete();
            });

            $schema = resolveTableSchema('fuel_stock_deliveries');

            if ($schema !== null && ! $this->constraintExists('fuel_stock_deliveries_qty_check')) {
                DB::statement(
                    "ALTER TABLE {$schema}.fuel_stock_deliveries ADD CONSTRAINT fuel_stock_deliveries_qty_check CHECK (quantity_minor > 0)"
                );
            }

            if ($schema !== null && ! $this->constraintExists('fuel_stock_deliveries_unit_check')) {
                DB::statement(
                    "ALTER TABLE {$schema}.fuel_stock_deliveries ADD CONSTRAINT fuel_stock_deliveries_unit_check CHECK (unit_code IN ('l', 'gal'))"
                );
            }

            if ($schema !== null && ! $this->constraintExists('fuel_stock_deliveries_status_check')) {
                DB::statement(
                    "ALTER TABLE {$schema}.fuel_stock_deliveries ADD CONSTRAINT fuel_stock_deliveries_status_check CHECK (status IN ('draft', 'received', 'rejected'))"
                );
            }
        }

        if (! schemaTableExists('fuel_reconciliation_reports')) {
            Schema::create('fuel_reconciliation_reports', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id');
                $table->date('report_date');
                $table->unsignedBigInteger('opening_stock_minor')->default(0);
                $table->unsignedBigInteger('deliveries_minor')->default(0);
                $table->unsignedBigInteger('sales_minor')->default(0);
                $table->unsignedBigInteger('expected_stock_minor')->default(0);
                $table->unsignedBigInteger('closing_stock_minor')->nullable();
                // variance = closing − expected (peut être négatif : écart).
                $table->bigInteger('variance_minor')->default(0);
                // pending_review | reviewed | approved
                $table->string('status', 20)->default('pending_review');
                $table->text('explanation')->nullable();
                $table->unsignedInteger('reviewed_by')->nullable();
                $table->timestampTz('reviewed_at')->nullable();
                $table->timestamps();

                // Un rapport par station et par jour → le job de rapprochement
                // est REJOUABLE (recalcul + upsert, zéro doublon).
                $table->unique(['company_id', 'station_id', 'report_date'], 'fuel_reconcil_company_station_date_unique');
                $table->index(['company_id', 'status'], 'fuel_reconcil_company_status_idx');

                $table->foreign(['station_id', 'company_id'], 'fuel_reconcil_station_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_stations')
                    ->cascadeOnDelete();
            });

            $schema = resolveTableSchema('fuel_reconciliation_reports');

            if ($schema !== null && ! $this->constraintExists('fuel_reconcil_status_check')) {
                DB::statement(
                    "ALTER TABLE {$schema}.fuel_reconciliation_reports ADD CONSTRAINT fuel_reconcil_status_check CHECK (status IN ('pending_review', 'reviewed', 'approved'))"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_reconciliation_reports');
        Schema::dropIfExists('fuel_stock_deliveries');
        Schema::dropIfExists('fuel_tank_stock_levels');
    }

    private function addIdCompanyUnique(string $table): void
    {
        if (schemaTableExists($table) && ! $this->constraintExists($table.'_id_company_unique')) {
            $schema = resolveTableSchema($table);
            DB::statement(
                "ALTER TABLE {$schema}.{$table} ADD CONSTRAINT {$table}_id_company_unique UNIQUE (id, company_id)"
            );
        }
    }

    private function constraintExists(string $name): bool
    {
        $row = DB::selectOne('SELECT 1 FROM pg_constraint WHERE conname = ?', [$name]);

        return $row !== null;
    }
};
