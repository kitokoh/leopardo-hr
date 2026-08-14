<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #1816 — F-20 : `actual_days_worked` depuis les logs de présence réels.
 *
 * Ajoute `pay_slips.has_attendance_data` (booléen, défaut false) : positionné à
 * true quand le bulletin a été calculé avec des jours distincts issus
 * d'`AttendanceLog` valides, false quand le moteur est retombé sur le prorata
 * contrat (aucun log de présence sur la période).
 *
 * Additive et idempotente : résolution de schéma via le search_path
 * (convention issue #1613) — les tenaints déjà migrés sont simplement no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = resolveTableSchema('pay_slips');

        if ($schema === null || schemaHasColumn('pay_slips', 'has_attendance_data')) {
            return;
        }

        Schema::table('pay_slips', function (Blueprint $table): void {
            $table->boolean('has_attendance_data')->default(false)->after('overtime_hours');
        });

        DB::statement(
            "COMMENT ON COLUMN {$schema}.pay_slips.has_attendance_data IS "
            ."'Issue #1816 : true = jours travaillés comptés depuis AttendanceLog, false = prorata contrat'"
        );
    }

    public function down(): void
    {
        $schema = resolveTableSchema('pay_slips');

        if ($schema === null || ! schemaHasColumn('pay_slips', 'has_attendance_data')) {
            return;
        }

        Schema::table('pay_slips', function (Blueprint $table): void {
            $table->dropColumn('has_attendance_data');
        });
    }
};
