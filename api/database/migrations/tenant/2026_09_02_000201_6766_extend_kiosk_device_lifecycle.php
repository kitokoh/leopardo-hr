<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BIO-005 (#6766) + BIO-006 (#6767) — cycle de vie & matrice de méthodes des
 * kiosques de pointage.
 *
 * `attendance_kiosks` gagne :
 *   - `site_id` : site d'affectation (lecture seule après provisioning — un
 *     kiosque ne peut pas changer de tenant/site par modification de payload) ;
 *   - `punch_methods` (json) : matrice des méthodes activées sur CE kiosque
 *     (device → défaut entreprise `kiosk.punch_methods.default` → toutes) ;
 *   - `revoked_at` / `revoked_by_employee_id` : révocation d'appareil
 *     (un appareil révoqué ne peut plus pointer ni synchroniser).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (resolveTableSchema('attendance_kiosks') === null) {
            return;
        }

        $schema = resolveTableSchema('attendance_kiosks');

        if ($schema === null) {
            return;
        }

        // Convention #1613 : pas d_appel Schéma-table au nom nu (piège F-17) —
        // ALTER résolu via le search_path.
        if (! schemaHasColumn('attendance_kiosks', 'site_id')) {
            DB::statement("ALTER TABLE {$schema}.attendance_kiosks ADD COLUMN site_id INTEGER NULL");
            DB::statement("CREATE INDEX attendance_kiosks_site_id_index ON {$schema}.attendance_kiosks (site_id)");
        }

        if (! schemaHasColumn('attendance_kiosks', 'punch_methods')) {
            DB::statement("ALTER TABLE {$schema}.attendance_kiosks ADD COLUMN punch_methods JSON NULL");
        }

        if (! schemaHasColumn('attendance_kiosks', 'revoked_at')) {
            DB::statement("ALTER TABLE {$schema}.attendance_kiosks ADD COLUMN revoked_at TIMESTAMP(0) WITH TIME ZONE NULL");
        }

        if (! schemaHasColumn('attendance_kiosks', 'revoked_by_employee_id')) {
            DB::statement("ALTER TABLE {$schema}.attendance_kiosks ADD COLUMN revoked_by_employee_id INTEGER NULL");
        }
    }

    public function down(): void
    {
        $schema = resolveTableSchema('attendance_kiosks');

        if ($schema === null) {
            return;
        }

        if (schemaHasColumn('attendance_kiosks', 'site_id')) {
            DB::statement("ALTER TABLE {$schema}.attendance_kiosks DROP COLUMN site_id");
        }

        if (schemaHasColumn('attendance_kiosks', 'punch_methods')) {
            DB::statement("ALTER TABLE {$schema}.attendance_kiosks DROP COLUMN punch_methods");
        }

        if (schemaHasColumn('attendance_kiosks', 'revoked_at')) {
            DB::statement("ALTER TABLE {$schema}.attendance_kiosks DROP COLUMN revoked_at");
        }

        if (schemaHasColumn('attendance_kiosks', 'revoked_by_employee_id')) {
            DB::statement("ALTER TABLE {$schema}.attendance_kiosks DROP COLUMN revoked_by_employee_id");
        }
    }
};
