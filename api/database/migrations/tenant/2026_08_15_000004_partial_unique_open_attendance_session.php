<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #2669 (QA 2026-08-15) — garde-fou DB contre les sessions de pointage
 * doubles : index unique PARTIEL sur `attendance_logs (employee_id, date)`
 * pour les lignes SANS check_out (session ouverte). Deux check-in parallèles
 * ne peuvent plus créer deux sessions ouvertes le même jour (le verrou
 * applicatif `lockForUpdate` de AttendanceService couvre la course ; cet
 * index est la ceinture de sécurité au niveau données).
 *
 * Les split-shifts (session_number 2+) restent possibles : la session 1 est
 * fermée (check_out non nul) avant l'ouverture de la session 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = (string) (DB::selectOne('SELECT current_schema() AS schema')?->schema ?? 'public');

        if ($schema === '') {
            return;
        }

        $indexName = 'attendance_logs_one_open_session_per_employee_day';

        $exists = DB::selectOne(
            'SELECT 1 FROM pg_indexes WHERE schemaname = ? AND indexname = ?',
            [$schema, $indexName]
        );

        if ($exists !== null) {
            return;
        }

        // Données héritées déjà invalides (deux sessions ouvertes le même
        // jour) : fermer la session la plus ANCIENNE (check_out = check_in,
        // durée nulle) pour que l'index unique partiel puisse être créé sans
        // échec. La session la plus récente reste ouverte.
        DB::statement(
            "UPDATE {$schema}.attendance_logs a
             SET check_out = a.check_in
             WHERE a.check_out IS NULL
               AND EXISTS (
                   SELECT 1 FROM {$schema}.attendance_logs b
                   WHERE b.employee_id = a.employee_id
                     AND b.date = a.date
                     AND b.check_out IS NULL
                     AND b.id > a.id
               )"
        );

        DB::statement(
            "CREATE UNIQUE INDEX {$indexName}
             ON {$schema}.attendance_logs (employee_id, date)
             WHERE check_out IS NULL"
        );
    }

    public function down(): void
    {
        $schema = (string) (DB::selectOne('SELECT current_schema() AS schema')?->schema ?? 'public');

        if ($schema === '') {
            return;
        }

        DB::statement("DROP INDEX IF EXISTS {$schema}.attendance_logs_one_open_session_per_employee_day");
    }
};
