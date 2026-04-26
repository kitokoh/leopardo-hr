<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `attendance_logs.method` etait declare en ENUM('mobile','qr','biometric','manual')
 * dans la migration tenant 0004 (2026_04_01_000103). Or les services kiosques
 * (`KioskAttendanceService`, `AttendanceService::importExternalPunch`) inserent
 * des valeurs supplementaires (`kiosk_fingerprint`, `kiosk_face`, `kiosk_mixed`,
 * `kiosk_offline`) introduites par les iterations APV L.05/L.07.
 *
 * Le test suite utilise `CreatesMvpSchema` qui declare `method` en VARCHAR, donc
 * les tests passent ; mais en production la contrainte ENUM rejette les inserts
 * et provoque un 500. On migre vers VARCHAR(30) avec un check applicatif.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE attendance_logs ALTER COLUMN method DROP DEFAULT');
            DB::statement('ALTER TABLE attendance_logs ALTER COLUMN method TYPE VARCHAR(30) USING method::text');
            DB::statement("ALTER TABLE attendance_logs ALTER COLUMN method SET DEFAULT 'mobile'");

            // Le type enum precedent (laravel le nomme attendance_logs_method_check
            // selon la convention) reste lie a une CHECK constraint sur certaines
            // versions de laravel ; on supprime explicitement la contrainte si
            // elle est presente pour eviter les regressions.
            $constraint = DB::selectOne("\n                select conname from pg_constraint c\n                join pg_class t on t.oid = c.conrelid\n                where t.relname = 'attendance_logs'\n                  and c.contype = 'c'\n                  and pg_get_constraintdef(c.oid) ilike '%method%'\n                limit 1\n            ");

            if ($constraint && property_exists($constraint, 'conname')) {
                DB::statement('ALTER TABLE attendance_logs DROP CONSTRAINT '.$constraint->conname);
            }

            return;
        }

        Schema::table('attendance_logs', function (Blueprint $table): void {
            $table->string('method', 30)->default('mobile')->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("UPDATE attendance_logs SET method = 'mobile' WHERE method NOT IN ('mobile','qr','biometric','manual')");
            DB::statement("ALTER TABLE attendance_logs ALTER COLUMN method DROP DEFAULT");
            DB::statement("ALTER TABLE attendance_logs ALTER COLUMN method TYPE VARCHAR(20)");
            DB::statement("ALTER TABLE attendance_logs ALTER COLUMN method SET DEFAULT 'mobile'");

            return;
        }

        Schema::table('attendance_logs', function (Blueprint $table): void {
            $table->string('method', 20)->default('mobile')->change();
        });
    }
};
