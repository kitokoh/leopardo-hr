<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ticket #761 — Ajouter option de pointage kiosque par clic ou photo.
 *
 * - attendance_mode_settings.punch_photo_mode : mode de pointage mobile
 *   configure par entreprise. Valeurs supportees:
 *     null  | 'kiosk'          => clic simple (comportement actuel, inchange)
 *           | 'photo_required' => une photo est obligatoire a chaque pointage
 *             (arrivee ET depart) sur mobile.
 * - attendance_logs.punch_photo_path : chemin de stockage (disk 'local')
 *   de la photo associee a ce pointage, si le mode photo_required est actif.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = resolveTableSchema('attendance_mode_settings');
        if ($schema !== null && ! schemaHasColumn('attendance_mode_settings', 'punch_photo_mode')) {
            Schema::table("{$schema}.attendance_mode_settings", function (Blueprint $table): void {
                // Valeurs: null | kiosk | photo_required
                $table->string('punch_photo_mode', 20)->nullable()->after('forced_mode');
            });
        }

        $schema = resolveTableSchema('attendance_logs');
        if ($schema !== null && ! schemaHasColumn('attendance_logs', 'punch_photo_path')) {
            Schema::table("{$schema}.attendance_logs", function (Blueprint $table): void {
                $table->string('punch_photo_path', 255)->nullable()->after('punch_meta');
            });
        }
    }

    public function down(): void
    {
        $schema = resolveTableSchema('attendance_logs');
        if ($schema !== null && schemaHasColumn('attendance_logs', 'punch_photo_path')) {
            Schema::table("{$schema}.attendance_logs", function (Blueprint $table): void {
                $table->dropColumn('punch_photo_path');
            });
        }

        $schema = resolveTableSchema('attendance_mode_settings');
        if ($schema !== null && schemaHasColumn('attendance_mode_settings', 'punch_photo_mode')) {
            Schema::table("{$schema}.attendance_mode_settings", function (Blueprint $table): void {
                $table->dropColumn('punch_photo_mode');
            });
        }
    }
};
