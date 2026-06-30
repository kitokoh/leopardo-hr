<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration SmartAttendance 04 — employee_location_events
 *
 * Log des événements de géolocalisation.
 * NE STOCKE PAS de positions en continu — uniquement les événements
 * significatifs (entrée/sortie de zone, consentement).
 *
 * Principe de minimisation RGPD : les coordonnées sont stockées
 * uniquement dans ce contexte professionnel borné.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_location_events')) {
            Schema::create('employee_location_events', function (Blueprint $table) {
                $table->bigIncrements('id');

                $table->unsignedInteger('employee_id');
                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();

                $table->uuid('company_id')->index();

                // Session GPS associée (nullable pour les events hors session)
                $table->unsignedBigInteger('geo_session_id')->nullable();
                $table->foreign('geo_session_id')->references('id')->on('geo_attendance_sessions')->nullOnDelete();

                // Type d'événement
                $table->string('event_type', 30);
                // zone_enter | zone_exit | session_start | session_end
                // consent_given | consent_revoked | geofence_error | outside_zone

                // Coordonnées GPS au moment de l'événement
                $table->decimal('latitude', 10, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
                $table->unsignedSmallInteger('accuracy_meters')->nullable();

                // Timestamp côté appareil (peut différer du created_at serveur)
                $table->timestampTz('device_timestamp')->nullable();

                // Métadonnées optionnelles (OS, version app, etc.)
                $table->jsonb('metadata')->default('{}');

                // Timestamp serveur
                $table->timestampTz('created_at')->useCurrent();

                // Index
                $table->index(['employee_id', 'event_type']);
                $table->index(['employee_id', 'created_at']);
                $table->index(['geo_session_id']);
            });

            DB::statement("COMMENT ON TABLE employee_location_events IS 'Log des événements GPS (entrée/sortie de zone uniquement). Pas de tracking continu. Minimisation RGPD.'");
            DB::statement("COMMENT ON COLUMN employee_location_events.device_timestamp IS 'Horodatage côté mobile. Peut différer de created_at en cas de sync offline.'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_location_events');
    }
};
