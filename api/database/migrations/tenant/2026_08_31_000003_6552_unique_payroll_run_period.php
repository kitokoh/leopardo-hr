<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #6552 — garde anti-doublon de période sur payroll_runs.
 *
 * L'index simple (company_id, period_start, period_end) permettait deux runs
 * `draft` pour la même période (double-clic / concurrence) → double paie
 * potentielle.
 *
 * Correctif : index UNIQUE PARTIEL `WHERE status <> 'cancelled'` — un run
 * annulé libère la période (on peut refaire le mois), les runs actifs
 * (draft→paid) sont uniques par (company_id, period_start, period_end).
 * Le perdant d'une course reçoit 23505, géré proprement par le contrôleur
 * (409 PERIOD_ALREADY_EXISTS).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('payroll_runs')) {
            return;
        }

        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS payroll_runs_company_period_unique_active
            ON payroll_runs (company_id, period_start, period_end)
            WHERE status <> \'cancelled\'
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS payroll_runs_company_period_unique_active');
    }
};
