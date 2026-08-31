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
 * Correctif : index UNIQUE PARTIEL `WHERE status = 'draft' AND
 * type = 'standard'` — le risque double-paie est la création CONCURRENTE de
 * deux brouillons (double-clic) ; le perdant d'une course reçoit 23505,
 * géré proprement par le contrôleur (409 PERIOD_ALREADY_EXISTS).
 *
 * Périmètre volontairement étroit : les flux légitimes créent plusieurs
 * runs VALIDATED/PAID pour la même période (déclarations CNAS/CNPS/CNSS,
 * exports), les runs de RÉGULARISATION (#1818, même période que l'original
 * par conception) et les runs annulés — tous exclus de la contrainte. La
 * garde applicative du contrôleur (store → 409 si un run non annulé existe
 * déjà) couvre le reste.
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
            WHERE status = \'draft\'
              AND type = \'standard\'
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS payroll_runs_company_period_unique_active');
    }
};
