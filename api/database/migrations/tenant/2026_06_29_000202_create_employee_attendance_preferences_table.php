<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration SmartAttendance 02 — employee_attendance_preferences
 *
 * Préférences de mode de pointage par employé (niveau 2).
 * Actif uniquement si attendance_mode_settings.forced_mode IS NULL
 * ET allow_employee_override = true pour cette company.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_attendance_preferences')) {
            Schema::create('employee_attendance_preferences', function (Blueprint $table) {
                $table->increments('id');

                $table->unsignedInteger('employee_id');
                // FK vers employees gérée au niveau applicatif (BelongsToCompany scope)
                // La FK explicite est omise ici car la migration peut s'exécuter avant
                // que la table employees ne soit présente dans certains environnements CI.

                $table->uuid('company_id'); // index ajouté ci-dessous, pas ici

                // Mode préféré de l'employé
                $table->string('preferred_mode', 20)->default('manual');
                // Valeurs: gps_auto | qr | manual

                // Consentement RGPD — obligatoire avant activation gps_auto
                $table->boolean('gps_consent_given')->default(false);
                $table->timestampTz('gps_consent_at')->nullable();

                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();

                // Un employé = une préférence
                $table->unique('employee_id');
                $table->index('company_id'); // index unique, pas de doublon avec ->index() ci-dessus
            });

            \Illuminate\Support\Facades\DB::statement(
                "COMMENT ON TABLE employee_attendance_preferences IS 'Préférence mode pointage individuel. Actif uniquement si l\'entreprise n\'impose pas de mode.'"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_attendance_preferences');
    }
};
