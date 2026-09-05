<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migration fantôme neutralisée (round 27, PM 2026-09-05) : deux implémentations
 * PARALLÈLES de la garde #6552 ont été fusionnées par le merge union.
 * Le schéma canonique est porté par 2026_08_31_000003 (index partiel
 * `payroll_runs_company_period_unique_active` sur (company_id, period_start,
 * period_end) WHERE status = 'draft' AND type = 'standard') : il empêche la
 * double paie sur double-clic tout en EXCLUANT les runs de régularisation
 * (#1818, même période que l'original par conception), les runs
 * validés/payés multiples (déclarations CNAS/CNPS/CNSS, exports) et les runs
 * annulés. Celle-ci (000215) créait un index plus large
 * (`payroll_runs_company_period_partial_unique`, status <> 'cancelled', avec
 * country_code) qui rendait IMPOSSIBLE la création d'une régularisation sur
 * une période déjà traitée (violation 23505 en test — cf. round 27). La
 * migration corrective 2026_09_05_000001 droppe l'index fautif sur les
 * environnements qui l'avaient déjà appliqué.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Volontairement vide — voir commentaire ci-dessus.
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS payroll_runs_company_period_partial_unique');
    }
};
