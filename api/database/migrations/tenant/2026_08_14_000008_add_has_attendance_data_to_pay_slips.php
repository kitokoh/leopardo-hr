<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Programme FOCUS — F-20 (issue #1816) : marquer chaque bulletin selon la
 * source de son `actual_days_worked`.
 *
 * Avant : `actual_days_worked` était toujours dérivé du prorata calendaire
 * (contrat × période du run). Depuis #1816, quand des logs de pointage
 * valides existent sur la période, le décompte vient des jours distincts
 * réellement pointés (AttendanceLog) — le prorata contrat ne reste qu'un
 * fallback. `pay_slips.has_attendance_data` permet au bulletin et aux
 * exports de savoir quelle source a été utilisée (auditabilité paie).
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = resolveTableSchema('pay_slips');
        if ($schema !== null && ! schemaHasColumn('pay_slips', 'has_attendance_data')) {
            Schema::table("{$schema}.pay_slips", function (Blueprint $table) {
                $table->boolean('has_attendance_data')->default(false)->after('overtime_hours');
            });
        }
    }

    public function down(): void
    {
        $schema = resolveTableSchema('pay_slips');
        if ($schema !== null && schemaHasColumn('pay_slips', 'has_attendance_data')) {
            Schema::table("{$schema}.pay_slips", function (Blueprint $table) {
                $table->dropColumn('has_attendance_data');
            });
        }
    }
};
