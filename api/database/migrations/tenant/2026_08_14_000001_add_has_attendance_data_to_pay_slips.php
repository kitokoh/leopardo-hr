<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Programme FOCUS — F-20 (issue #1816) : présence réelle → paie.
 *
 * Ajoute `has_attendance_data` à `pay_slips` : indique si les jours travaillés
 * du bulletin proviennent des logs de présence réels (AttendanceLog) ou d'un
 * fallback prorata contrat. Migration additive et idempotente (pattern
 * schema-aware du module Payroll, cf. 2026_08_09_000001_add_locking_to_payroll_runs.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = resolveTableSchema('pay_slips');

        if ($schema !== null && ! schemaHasColumn('pay_slips', 'has_attendance_data')) {
            Schema::table("{$schema}.pay_slips", function (Blueprint $table): void {
                $table->boolean('has_attendance_data')->default(false)->after('overtime_hours');
            });
        }
    }

    public function down(): void
    {
        $schema = resolveTableSchema('pay_slips');

        if ($schema !== null && schemaHasColumn('pay_slips', 'has_attendance_data')) {
            Schema::table("{$schema}.pay_slips", function (Blueprint $table): void {
                $table->dropColumn('has_attendance_data');
            });
        }
    }
};
