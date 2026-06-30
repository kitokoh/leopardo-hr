<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trait CreatesSmartAttendanceSchema
 *
 * Crée les tables nécessaires au module SmartAttendance pour les tests Feature.
 * À utiliser en complément de CreatesMvpSchema.
 */
trait CreatesSmartAttendanceSchema
{
    /**
     * Crée les quatre tables du module SmartAttendance.
     * Appeler après setUpMvpSchema() dans setUp().
     */
    protected function createSmartAttendanceTables(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO shared_tenants,public');
        }

        if (! Schema::hasTable('attendance_mode_settings')) {
            Schema::create('attendance_mode_settings', function (Blueprint $table): void {
                $table->increments('id');
                $table->uuid('company_id')->unique()->index();
                $table->string('forced_mode', 20)->nullable();
                $table->boolean('gps_enabled')->default(false);
                $table->decimal('latitude', 10, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
                $table->unsignedSmallInteger('radius_meters')->default(100);
                $table->boolean('allow_employee_override')->default(true);
                $table->unsignedInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('employee_attendance_preferences')) {
            Schema::create('employee_attendance_preferences', function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('employee_id');
                $table->uuid('company_id')->index();
                $table->string('preferred_mode', 20)->default('manual');
                $table->boolean('gps_consent_given')->default(false);
                $table->timestamp('gps_consent_at')->nullable();
                $table->timestamps();
                $table->unique('employee_id');
            });
        }

        if (! Schema::hasTable('geo_attendance_sessions')) {
            Schema::create('geo_attendance_sessions', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedInteger('employee_id');
                $table->uuid('company_id')->index();
                $table->unsignedInteger('site_id')->nullable();
                $table->timestamp('started_at');
                $table->timestamp('ended_at')->nullable();
                $table->unsignedInteger('duration_seconds')->nullable();
                $table->decimal('check_in_lat', 10, 8);
                $table->decimal('check_in_lng', 11, 8);
                $table->unsignedSmallInteger('check_in_accuracy_meters')->nullable();
                $table->decimal('check_out_lat', 10, 8)->nullable();
                $table->decimal('check_out_lng', 11, 8)->nullable();
                $table->unsignedSmallInteger('check_out_accuracy_meters')->nullable();
                $table->string('status', 20)->default('detected');
                $table->unsignedInteger('attendance_log_id')->nullable();
                $table->unsignedInteger('validated_by')->nullable();
                $table->timestamp('validated_at')->nullable();
                $table->text('validation_note')->nullable();
                $table->timestamps();

                $table->index(['employee_id', 'status']);
                $table->index(['company_id', 'status']);
            });
        }

        if (! Schema::hasTable('employee_location_events')) {
            Schema::create('employee_location_events', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedInteger('employee_id');
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('geo_session_id')->nullable();
                $table->string('event_type', 30);
                $table->decimal('latitude', 10, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
                $table->unsignedSmallInteger('accuracy_meters')->nullable();
                $table->timestamp('device_timestamp')->nullable();
                if (DB::getDriverName() === 'pgsql') {
                    $table->jsonb('metadata')->default(DB::raw("'{}'::jsonb"));
                } else {
                    $table->json('metadata')->default('{}');
                }
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    /**
     * Supprime les tables SmartAttendance (pour SQLite uniquement).
     */
    protected function dropSmartAttendanceTables(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // En PostgreSQL les tables sont nettoyées par le trait principal
            return;
        }

        DB::statement('DROP TABLE IF EXISTS "employee_location_events"');
        DB::statement('DROP TABLE IF EXISTS "geo_attendance_sessions"');
        DB::statement('DROP TABLE IF EXISTS "employee_attendance_preferences"');
        DB::statement('DROP TABLE IF EXISTS "attendance_mode_settings"');
    }
}
