<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Programme FOCUS — F-20 (#1816) : trace si le bulletin a été calculé à
 * partir des logs de présence réels (AttendanceLog) ou du fallback prorata
 * contrat. Colonne additive, idempotente, résolue via le search_path tenant
 * (même pattern que 2026_08_09_000001_add_locking_to_payroll_runs.php).
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
