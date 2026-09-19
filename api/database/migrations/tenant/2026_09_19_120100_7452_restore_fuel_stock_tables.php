<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7452 — Restauration des tables Fuel perdues par la consolidation #7572.
 *
 * La dédup #7572 (commit e53b1ab59) a retiré de
 * `2026_08_30_000500_5803_create_fuel_stock_tables.php` les déclarations de
 * `fuel_deliveries`, `fuel_stock_movements` et `fuel_stock_reconciliations`
 * (générations concurrentes de la réécriture 1a4ccae41), alors qu'aucune autre
 * migration ne déclare ces trois tables. Elles restent pourtant utilisées par
 * du code vivant : `FuelMetricsController` (compte les rapprochements en
 * exception), `FuelReportingService`, `FuelAccountingContractPublisher`,
 * modèles `FuelDelivery`/`FuelStockMovement`/`FuelStockReconciliation` —
 * mesuré : 6 × `relation "fuel_stock_reconciliations" does not exist` dans
 * `tests/Feature/Fuel` (FuelObservabilityApiTest, FuelOutboxApiTest).
 *
 * Ce correctif restaure les trois déclarations d'origine (9d5979734), gardées
 * par `schemaTableExists` (idempotent : no-op sur une base qui les porte
 * encore). Les CHECK constraints correspondants existent toujours dans
 * `addChecks()` de la migration 000500 mais ne s'appliquaient plus faute de
 * table : ils sont reposés ici avec les mêmes noms (gardés par pg_constraint).
 *
 * Relâche aussi le NOT NULL de `fuel_meter_intervals.previous_reading_id` /
 * `current_reading_id` : les intervalles d'anomalie créés par le module
 * d'alertes (FuelAlertApiTest/FuelAlertServiceTest) ne sont pas rattachés à
 * deux relevés — l'index unique (company_id, previous_reading_id,
 * current_reading_id) reste opérant pour les intervalles réels (les NULL n'y
 * sont pas contraignants).
 *
 * @see https://github.com/kitokoh/leopardo-hr/issues/7452
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_deliveries')) {
            Schema::create('fuel_deliveries', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id')->index();
                $table->unsignedBigInteger('tank_id')->nullable()->index();

                $table->string('product_type', 40);
                $table->bigInteger('quantity_minor');
                $table->string('supplier', 160)->nullable();
                $table->string('reference_number', 80)->nullable();
                $table->string('status', 20)->default('received'); // draft|received|verified
                $table->dateTime('delivered_at');
                $table->string('idempotency_key', 64);
                $table->unsignedInteger('received_by')->nullable();
                $table->unsignedInteger('verified_by')->nullable();
                $table->dateTime('verified_at')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'idempotency_key'], 'fuel_deliveries_idem_key_unique');

                // FK composite anti cross-tenant (pattern FUEL-002/003).
                $table->foreign(['station_id', 'company_id'], 'fuel_deliveries_station_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_stations')
                    ->cascadeOnDelete();
                $table->foreign(['tank_id', 'company_id'], 'fuel_deliveries_tank_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_tanks')
                    ->nullOnDelete();
            });
        }

        if (! schemaTableExists('fuel_stock_movements')) {
            Schema::create('fuel_stock_movements', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id')->index();
                $table->unsignedBigInteger('tank_id')->nullable()->index();

                $table->string('product_type', 40);
                $table->bigInteger('quantity_minor');
                $table->string('direction', 10); // in|out
                $table->string('reason', 30); // delivery|sale|adjustment|opening
                $table->string('reference_type', 30)->nullable(); // delivery|sale|reconciliation
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->dateTime('movement_at');
                $table->string('idempotency_key', 64)->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'idempotency_key'], 'fuel_stock_movements_idem_key_unique');

                $table->foreign(['station_id', 'company_id'], 'fuel_stock_movements_station_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_stations')
                    ->cascadeOnDelete();
                $table->foreign(['tank_id', 'company_id'], 'fuel_stock_movements_tank_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_tanks')
                    ->nullOnDelete();
            });
        }

        if (! schemaTableExists('fuel_stock_reconciliations')) {
            Schema::create('fuel_stock_reconciliations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id')->index();

                $table->string('product_type', 40);
                $table->date('period_start');
                $table->date('period_end');
                // pending_measurement|completed|exception
                $table->string('status', 24)->default('pending_measurement');
                $table->bigInteger('opening_minor')->default(0);
                $table->bigInteger('delivered_minor')->default(0);
                $table->bigInteger('sold_minor')->default(0);
                $table->bigInteger('metered_delta_minor')->default(0);
                $table->bigInteger('measured_close_minor')->nullable();
                $table->bigInteger('theoretical_close_minor')->default(0);
                $table->bigInteger('variance_minor')->default(0);
                $table->bigInteger('variance_tolerance_minor')->default(0);
                $table->jsonb('explanation')->nullable();
                $table->string('idempotency_key', 64);
                $table->unsignedInteger('started_by')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->timestamps();

                $table->unique(
                    ['company_id', 'station_id', 'product_type', 'period_start', 'period_end', 'idempotency_key'],
                    'fuel_reconciliations_replay_unique'
                );

                $table->foreign(['station_id', 'company_id'], 'fuel_reconciliations_station_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_stations')
                    ->cascadeOnDelete();
            });
        }

        $this->addChecks();

        // Intervalles d'anomalie sans relevés rattachés (module alertes).
        foreach (['previous_reading_id', 'current_reading_id'] as $column) {
            if (schemaTableExists('fuel_meter_intervals') && schemaHasColumn('fuel_meter_intervals', $column)) {
                DB::statement("ALTER TABLE fuel_meter_intervals ALTER COLUMN {$column} DROP NOT NULL");
            }
        }
    }

    public function down(): void
    {
        // Forward-only : ces tables portent des données métier (livraisons,
        // mouvements de stock audités) — pas de DROP destructif, et remettre
        // les NOT NULL exigerait des valeurs que les intervalles d'anomalie
        // n'ont pas.
    }

    /**
     * CHECKs de la migration d'origine (mêmes noms que `addChecks()` de
     * 2026_08_30_000500, gardés par pg_constraint : no-op si déjà posés).
     */
    private function addChecks(): void
    {
        foreach ([
            'fuel_deliveries' => [
                'fuel_deliveries_status_check' => "status IN ('draft', 'received', 'verified')",
                'fuel_deliveries_quantity_check' => 'quantity_minor > 0',
            ],
            'fuel_stock_movements' => [
                'fuel_stock_movements_direction_check' => "direction IN ('in', 'out')",
                'fuel_stock_movements_reason_check' => "reason IN ('delivery', 'sale', 'adjustment', 'opening')",
                'fuel_stock_movements_quantity_check' => 'quantity_minor > 0',
            ],
            'fuel_stock_reconciliations' => [
                'fuel_reconciliations_status_check' => "status IN ('pending_measurement', 'completed', 'exception')",
                'fuel_reconciliations_period_check' => 'period_end >= period_start',
            ],
        ] as $table => $constraints) {
            $schema = resolveTableSchema($table);

            if ($schema === null) {
                continue;
            }

            foreach ($constraints as $name => $check) {
                if (DB::selectOne('SELECT 1 FROM pg_constraint WHERE conname = ?', [$name]) !== null) {
                    continue;
                }

                DB::statement("ALTER TABLE {$schema}.{$table} ADD CONSTRAINT {$name} CHECK ({$check})");
            }
        }
    }
};
