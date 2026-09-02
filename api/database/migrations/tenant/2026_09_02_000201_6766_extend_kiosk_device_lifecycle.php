<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BIO-005 (#6766) + BIO-006 (#6767) — cycle de vie & matrice de méthodes des
 * kiosques de pointage.
 *
 * `attendance_kiosks` gagne :
 *   - `site_id` : site d'affectation (lecture seule après provisioning — un
 *     kiosque ne peut pas changer de tenant/site par modification de payload) ;
 *   - `punch_methods` (json) : matrice des méthodes activées sur CE kiosque
 *     (device → défaut entreprise `kiosk.punch_methods.default` → toutes) ;
 *   - `revoked_at` / `revoked_by_employee_id` : révocation d'appareil
 *     (un appareil révoqué ne peut plus pointer ni synchroniser).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('attendance_kiosks')) {
            return;
        }

        Schema::table('attendance_kiosks', function (Blueprint $table): void {
            $table->unsignedInteger('site_id')->nullable()->index();
            $table->json('punch_methods')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->unsignedInteger('revoked_by_employee_id')->nullable();
        });
    }

    public function down(): void
    {
        if (! schemaTableExists('attendance_kiosks')) {
            return;
        }

        Schema::table('attendance_kiosks', function (Blueprint $table): void {
            $table->dropColumn(['site_id', 'punch_methods', 'revoked_at', 'revoked_by_employee_id']);
        });
    }
};
