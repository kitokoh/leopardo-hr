<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration SmartAttendance 01 — attendance_mode_settings
 *
 * Configuration du mode de pointage par entreprise (niveau 1).
 * Une seule ligne par company (unique constraint).
 * Si forced_mode IS NULL → les employés choisissent individuellement (niveau 2).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_mode_settings')) {
            Schema::create('attendance_mode_settings', function (Blueprint $table) {
                $table->increments('id');

                // Référence company (UUID, isolé par tenant)
                $table->uuid('company_id')->unique()->index();

                // Mode forcé — NULL = pas de mode entreprise, les employés choisissent
                $table->string('forced_mode', 20)->nullable();
                // Valeurs supportées: null | gps_auto | qr | manual | mixed

                // Géofencing entreprise (utilisé si aucun site assigné à l'employé)
                $table->boolean('gps_enabled')->default(false);
                $table->decimal('latitude', 10, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
                $table->unsignedSmallInteger('radius_meters')->default(100);

                // Autoriser les employés à surcharger le mode (si forced_mode IS NULL)
                $table->boolean('allow_employee_override')->default(true);

                // Audit
                $table->unsignedInteger('updated_by')->nullable();
                $table->foreign('updated_by')->references('id')->on('employees')->nullOnDelete();
                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();
            });

            \Illuminate\Support\Facades\DB::statement(
                "COMMENT ON TABLE attendance_mode_settings IS 'Config mode pointage par entreprise. forced_mode NULL = choix libre employé.'"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_mode_settings');
    }
};
