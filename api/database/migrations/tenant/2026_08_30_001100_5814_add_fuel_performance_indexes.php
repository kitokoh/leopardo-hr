<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #5814 (FUEL-020) — sécurité, performance et observabilité.
 *
 * Index composites pour les chemins de lecture chauds (reporting,
 * rapprochement, alertes, exports) — tenant-first (company_id en tête),
 * idempotents (CREATE INDEX IF NOT EXISTS), aucun changement de schéma.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            // Reporting volumes par pompe / rapprochement : deltas par compteur
            // et période.
            'fuel_meter_intervals' => [
                'fuel_intervals_company_meter_calculated_idx' => '(company_id, meter_id, calculated_at)',
            ],
            // Historique des relevés par compteur (corrections, rejeu).
            'fuel_meter_readings' => [
                'fuel_readings_company_meter_captured_idx' => '(company_id, meter_id, captured_at_utc)',
            ],
            // Journal de stock par station et période (rapprochement, reporting).
            'fuel_stock_movements' => [
                'fuel_movements_company_station_moved_idx' => '(company_id, station_id, movement_at)',
            ],
            // Ventes par shift (affectations du jour).
            'fuel_shift_assignments' => [
                'fuel_assignments_company_shift_date_idx' => '(company_id, shift_id, assignment_date)',
            ],
        ] as $table => $indexes) {
            $schema = resolveTableSchema($table);

            if ($schema === null) {
                continue;
            }

            foreach ($indexes as $name => $definition) {
                DB::statement(
                    "CREATE INDEX IF NOT EXISTS {$name} ON {$schema}.{$table} {$definition}"
                );
            }
        }
    }

    public function down(): void
    {
        // Index additifs : pas de rollback destructeur (les index restent
        // utiles à l'historique déjà écrit). Suppression optionnelle :
        foreach ([
            'fuel_meter_intervals' => 'fuel_intervals_company_meter_calculated_idx',
            'fuel_meter_readings' => 'fuel_readings_company_meter_captured_idx',
            'fuel_stock_movements' => 'fuel_movements_company_station_moved_idx',
            'fuel_shift_assignments' => 'fuel_assignments_company_shift_date_idx',
        ] as $table => $name) {
            $schema = resolveTableSchema($table);

            if ($schema === null) {
                continue;
            }

            DB::statement("DROP INDEX IF EXISTS {$schema}.{$name}");
        }
    }
};
