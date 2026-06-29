<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration SmartAttendance 03 — geo_attendance_sessions
 *
 * Table centrale du module SmartAttendance.
 * Chaque session GPS (ouverte à l'entrée, fermée à la sortie) y est stockée.
 * Elle devient un attendance_log UNIQUEMENT après approbation manager/RH.
 *
 * Cycle de vie des statuts :
 *   detected → pending_validation → approved | rejected
 *                                → cancelled (annulation manuelle ou doublon)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('geo_attendance_sessions')) {
            Schema::create('geo_attendance_sessions', function (Blueprint $table) {
                $table->bigIncrements('id');

                $table->unsignedInteger('employee_id');
                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();

                $table->uuid('company_id')->index();

                // Site détecté (si l'employé est rattaché à un site)
                $table->unsignedInteger('site_id')->nullable();
                $table->foreign('site_id')->references('id')->on('sites')->nullOnDelete();

                // Horodatages de la session
                $table->timestampTz('started_at');
                $table->timestampTz('ended_at')->nullable();
                $table->unsignedInteger('duration_seconds')->nullable()
                      ->comment('Calculé automatiquement à la fermeture de la session');

                // Coordonnées GPS au moment de l'entrée
                $table->decimal('check_in_lat', 10, 8);
                $table->decimal('check_in_lng', 11, 8);
                $table->unsignedSmallInteger('check_in_accuracy_meters')->nullable();

                // Coordonnées GPS au moment de la sortie
                $table->decimal('check_out_lat', 10, 8)->nullable();
                $table->decimal('check_out_lng', 11, 8)->nullable();
                $table->unsignedSmallInteger('check_out_accuracy_meters')->nullable();

                // Statut du cycle de validation
                $table->string('status', 20)->default('detected');
                // detected | pending_validation | approved | rejected | cancelled

                // Lien vers attendance_log créé lors de l'approbation
                $table->unsignedInteger('attendance_log_id')->nullable();
                $table->foreign('attendance_log_id')->references('id')->on('attendance_logs')->nullOnDelete();

                // Validation
                $table->unsignedInteger('validated_by')->nullable();
                $table->foreign('validated_by')->references('id')->on('employees')->nullOnDelete();
                $table->timestampTz('validated_at')->nullable();
                $table->text('validation_note')->nullable();

                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();

                // Performance indexes
                $table->index(['employee_id', 'status']);
                $table->index(['company_id', 'status']);
                $table->index(['employee_id', 'started_at']);
                $table->index(['company_id', 'started_at']);
            });

            DB::statement("COMMENT ON TABLE geo_attendance_sessions IS 'Sessions de présence GPS automatiques. Génère un attendance_log après approbation manager/RH.'");
            DB::statement("COMMENT ON COLUMN geo_attendance_sessions.status IS 'detected=session ouverte|pending_validation=fermée,en attente|approved=validée|rejected=refusée|cancelled=annulée'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_attendance_sessions');
    }
};
