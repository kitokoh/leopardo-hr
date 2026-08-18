<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Audit Enterprise 2026 (point 18) — index manquant sur
 * `attendance_logs.created_at`.
 *
 * Les requêtes de paie (PayrollCalculator::computeWorkedDays, rapports de
 * pointage) filtrent/agrègent par (company_id, date) et trient par
 * `created_at` (sessions de pointage) ; l'index composite #3947 couvre
 * (company_id, employee_id) mais aucune index ne sert les fenêtres
 * temporelles pures sur `created_at`.
 *
 * F-17 (#1595/#1933) : accès qualifiés par schéma résolu via
 * `resolveTableSchema` (convention #1613) — idempotent, rejouable sur Render.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = resolveTableSchema('attendance_logs');

        if ($schema === null) {
            return; // Table absente dans ce contexte (CI partielle).
        }

        DB::statement(sprintf(
            'CREATE INDEX IF NOT EXISTS attendance_logs_created_at_idx ON %s (created_at)',
            $schema.'.attendance_logs'
        ));
    }

    public function down(): void
    {
        $schema = resolveTableSchema('attendance_logs');

        if ($schema === null) {
            return;
        }

        DB::statement(sprintf(
            'DROP INDEX IF EXISTS %s',
            $schema.'.attendance_logs_created_at_idx'
        ));
    }
};
