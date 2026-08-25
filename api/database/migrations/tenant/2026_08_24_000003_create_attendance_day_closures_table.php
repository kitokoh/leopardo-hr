<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #5265 — Pointage 100 % : fermeture de journée (verrouillage + validation).
 *
 * Complémentaire du verrouillage de PÉRIODE mensuelle (#5267,
 * `attendance_period_closures` — corrections) : ici le verrou est QUOTIDIEN
 * et par employé — un jour clos ne reçoit plus aucun nouveau pointage
 * (check-in/check-out, import externe, approbation de session géo) → garde
 * 409 `ATTENDANCE_DAY_CLOSED`. Un verrou peut être levé (unlock) ; la
 * validation est un acte distinct (review manager/RH).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('attendance_day_closures')) {
            Schema::create('attendance_day_closures', function (Blueprint $table) {
                $table->bigIncrements('id');

                $table->uuid('company_id')->index();
                $table->unsignedInteger('employee_id');
                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();

                // Date locale entreprise (même convention que attendance_logs.date).
                $table->date('date');

                // locked = verrouillé (plus de pointages) ; validated = verrouillé + relu par un manager.
                $table->string('status', 20)->default('locked');

                $table->unsignedInteger('locked_by')->nullable();
                $table->foreign('locked_by')->references('id')->on('employees')->nullOnDelete();
                $table->timestampTz('locked_at')->useCurrent();

                $table->unsignedInteger('validated_by')->nullable();
                $table->foreign('validated_by')->references('id')->on('employees')->nullOnDelete();
                $table->timestampTz('validated_at')->nullable();

                $table->text('note')->nullable();

                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();

                // Un seul verrou par (entreprise, employé, jour).
                $table->unique(['company_id', 'employee_id', 'date'], 'attendance_day_closures_company_employee_date_unique');
                $table->index(['company_id', 'date']);
            });

            DB::statement("COMMENT ON TABLE attendance_day_closures IS 'Fermeture de journée du pointage : un jour clos refuse tout nouveau pointage (409 ATTENDANCE_DAY_CLOSED).'");
            DB::statement("COMMENT ON COLUMN attendance_day_closures.status IS 'locked=verrouillé|validated=verrouillé et validé par un manager'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_day_closures');
    }
};
