<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5245 (Programme 100 %, wave W1) — intégration Absence/Attendance → paie :
 * persist sur chaque bulletin les entrées de travail RÉELLES utilisées par le
 * calcul (congés payés pris, congés sans solde, jours fériés payés) afin que
 * le run (simulation) expose le détail par employé et que l'audit paie garde
 * une trace exacte des entrées consommées.
 *
 * Ces colonnes sont un SNAPSHOT du calcul (remplies au calculate, comme
 * working_days/actual_days_worked) : elles ne pilotent aucune règle, elles
 * documentent le bulletin. Aucune rétro-migration nécessaire pour les runs
 * historiques (défaut 0.0 → bloc « attendance » absent du contrat API pour
 * les vieux bulletins, additive).
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = resolveTableSchema('pay_slips');
        if ($schema === null) {
            return;
        }

        if (! schemaHasColumn('pay_slips', 'paid_leave_days')) {
            Schema::table("{$schema}.pay_slips", function (Blueprint $table): void {
                $table->decimal('paid_leave_days', 6, 2)->default(0)->after('overtime_hours');
            });
        }

        if (! schemaHasColumn('pay_slips', 'unpaid_leave_days')) {
            Schema::table("{$schema}.pay_slips", function (Blueprint $table): void {
                $table->decimal('unpaid_leave_days', 6, 2)->default(0)->after('paid_leave_days');
            });
        }

        if (! schemaHasColumn('pay_slips', 'public_holiday_days')) {
            Schema::table("{$schema}.pay_slips", function (Blueprint $table): void {
                $table->decimal('public_holiday_days', 6, 2)->default(0)->after('unpaid_leave_days');
            });
        }
    }

    public function down(): void
    {
        $schema = resolveTableSchema('pay_slips');
        if ($schema === null) {
            return;
        }

        foreach (['public_holiday_days', 'unpaid_leave_days', 'paid_leave_days'] as $column) {
            if (schemaHasColumn('pay_slips', $column)) {
                Schema::table("{$schema}.pay_slips", function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
