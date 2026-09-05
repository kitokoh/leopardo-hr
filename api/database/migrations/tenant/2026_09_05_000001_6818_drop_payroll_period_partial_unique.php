<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Correction de la garde #6552 (round 27, PM 2026-09-05) : droppe l'index
 * fautif `payroll_runs_company_period_partial_unique` (créé par la migration
 * fantôme 2026_08_31_000215 neutralisée) sur les environnements qui l'avaient
 * déjà appliqué. Cet index (company_id, period_start, period_end,
 * country_code WHERE status <> 'cancelled') interdisait les runs de
 * régularisation (#1818, même période que l'original par conception) —
 * violation 23505 systématique. La garde anti-double-paie canonique reste
 * l'index partiel `payroll_runs_company_period_unique_active` (000003 :
 * draft + standard uniquement). Idempotent (IF EXISTS) — no-op sur les bases
 * fraîches qui n'ont jamais créé l'index.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS payroll_runs_company_period_partial_unique');
    }

    public function down(): void
    {
        // L'index fautif n'est pas recréé (voir neutralisation de 000215).
    }
};
