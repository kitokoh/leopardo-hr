<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5803 (FUEL-009) — stocks, cuves et rapprochement.
 *
 * Trois tables additives, toutes tenant-scoped (company_id uuid indexé) :
 *
 * - `fuel_deliveries` : livraisons de carburant reçues par une station
 *   (statut draft → received → verified). `idempotency_key` unique par
 *   tenant : un rejeu réseau ne crée jamais deux livraisons.
 * - `fuel_stock_movements` : journal des entrées/sorties de stock
 *   (direction in|out, raison delivery|sale|adjustment|opening). Aucun
 *   ajustement silencieux : chaque mouvement est explicite, audité
 *   (created_by) et traçable vers sa référence métier (reference_type +
 *   reference_id).
 * - `fuel_stock_reconciliations` : rapports de rapprochement compteurs ↔
 *   ventes ↔ stock. Rejouables : un couple (station, produit, période,
 *   clé d'idempotence) identique retourne le rapport existant, jamais un
 *   doublon. `explanation` conserve chaque composante du calcul pour un
 *   écart toujours explicable.
 *
 * Volumes en unités mineures entières (convention FuelStation : la valeur
 * d'un compteur `reading_value_minor` est en 10^-precision_scale unités) —
 * jamais de flottants métier dans les agrégats de stock.
 *
 * FKs composites (x, company_id) → fuel_stations(id, company_id) : aucun
 * rattachement cross-tenant possible (pattern FUEL-002/003).
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
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_stock_reconciliations');
        Schema::dropIfExists('fuel_stock_movements');
        Schema::dropIfExists('fuel_deliveries');
    }

    private function constraintExists(string $name): bool
    {
        $row = DB::selectOne('SELECT 1 FROM pg_constraint WHERE conname = ?', [$name]);

        return $row !== null;
    }

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
                if ($this->constraintExists($name)) {
                    continue;
                }

                DB::statement("ALTER TABLE {$schema}.{$table} ADD CONSTRAINT {$name} CHECK ({$check})");
            }
        }
    }
};
