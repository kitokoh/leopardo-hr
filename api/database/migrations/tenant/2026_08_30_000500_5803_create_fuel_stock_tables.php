<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5803 (FUEL-009) — stocks, cuves et rapprochement FuelStation.
 *
 * `fuel_tank_deliveries` : livraisons de carburant dans une cuve (append-only).
 * Idempotence par `external_id` UNIQUE par tenant (rejeu = relecture, zéro
 * doublon). `quantity_minor` en unités mineures entières (jamais de flottant
 * métier) ; `unit_price_minor` optionnel (coût moyen pondéré hors périmètre :
 * le COGS est un contrat Accounting, FUEL-015).
 *
 * `fuel_reconciliation_runs` : passe de rapprochement d'une station pour une
 * date donnée. Un seul run PAR (company_id, station_id, run_date) — la
 * contrainte unique rend le job de rapprochement rejouable : rejouer la même
 * date renvoie le run existant (zéro doublon). `summary` jsonb porte le
 * détail par cuve : théorique attendu (ouverture + livraisons − ventes) vs
 * mesuré (current_level_minor) et écart — AUCUN ajustement silencieux :
 * l'écart est rapporté, jamais corrigé en base.
 *
 * Toutes les données sont tenant-scoped (`company_id` non nullable) ; FKs
 * composites (x, company_id) anti cross-tenant (pattern FUEL-002/003).
 * Migration additive + idempotente (garde schemaTableExists #1962/#5431).
 */
return new class extends Migration
{
    public function up(): void
    {
        // FUEL-009 : la FK composite (tank_id, company_id) exige la contrainte
        // UNIQUE (id, company_id) sur fuel_tanks — ajoutée additivement si la
        // migration FUEL-003 (issue #5797) ne l'a pas posée.
        if (schemaTableExists('fuel_tanks') && ! $this->uniqueExists('fuel_tanks_id_company_unique')) {
            $schema = resolveTableSchema('fuel_tanks');
            if ($schema !== null) {
                DB::statement(
                    "ALTER TABLE {$schema}.fuel_tanks ADD CONSTRAINT fuel_tanks_id_company_unique UNIQUE (id, company_id)"
                );
            }
        }

        if (! schemaTableExists('fuel_tank_deliveries')) {
            Schema::create('fuel_tank_deliveries', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('tank_id')->index();

                $table->bigInteger('quantity_minor');
                $table->bigInteger('unit_price_minor')->nullable();
                $table->timestampTz('delivered_at')->useCurrent();
                $table->string('external_id', 120)->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();

                $table->unique(['company_id', 'external_id'], 'fuel_tank_deliveries_ext_unique');
                $table->index(['company_id', 'tank_id', 'delivered_at'], 'fuel_tank_deliveries_tank_delivered_idx');

                $table->foreign(['tank_id', 'company_id'], 'fuel_tank_deliveries_tank_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_tanks')
                    ->cascadeOnDelete();
            });

            DB::statement("COMMENT ON TABLE fuel_tank_deliveries IS 'Livraisons de carburant par cuve (append-only, idempotentes par external_id) — FUEL-009 (#5803).'");
        }

        if (! schemaTableExists('fuel_reconciliation_runs')) {
            Schema::create('fuel_reconciliation_runs', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id')->index();
                $table->date('run_date');

                // pending | running | completed | failed
                $table->string('status', 20)->default('pending');
                $table->jsonb('summary')->nullable();
                $table->timestampTz('started_at')->nullable();
                $table->timestampTz('finished_at')->nullable();
                $table->string('last_error', 500)->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();

                // Rejouable : un seul run par (station, date).
                $table->unique(['company_id', 'station_id', 'run_date'], 'fuel_reconciliation_runs_unique');

                $table->foreign(['station_id', 'company_id'], 'fuel_reconciliation_runs_station_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_stations')
                    ->cascadeOnDelete();
            });

            DB::statement("COMMENT ON TABLE fuel_reconciliation_runs IS 'Passe de rapprochement stock d une station par date (rejouable, écart rapporté jamais ajusté) — FUEL-009 (#5803).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_reconciliation_runs');
        Schema::dropIfExists('fuel_tank_deliveries');
    }

    private function uniqueExists(string $constraint): bool
    {
        $schema = resolveTableSchema('fuel_tanks');
        if ($schema === null) {
            return true;
        }

        return DB::selectOne(
            'SELECT 1
               FROM pg_constraint c
               JOIN pg_class t ON t.oid = c.conrelid
               JOIN pg_namespace n ON n.oid = t.relnamespace
              WHERE c.conname = ?
                AND n.nspname = ?
              LIMIT 1',
            [$constraint, $schema]
        ) !== null;
    }
};
