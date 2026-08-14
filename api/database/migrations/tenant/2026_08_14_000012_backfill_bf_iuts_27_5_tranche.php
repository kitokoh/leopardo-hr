<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #2003 — backfill de la tranche IUTS BF 27,5 % (> 6 000 000 FCFA/an)
 * dans les tax_slabs SEEDÉS en base.
 *
 * Contexte : #1972 a rétabli la 6e tranche dans `CedeaoPayrollRules::
 * defaultTaxSlabs()`, mais les lignes nationales (company_id NULL)
 * seedées depuis #1829 gardaient 5 tranches — `taxSlabs()` résolvant la
 * base avant le code, le re-seed était un no-op silencieux et les tenants
 * BF > ~500 000 FCFA/mois restaient sous-imposés (marginal 23,6 %).
 *
 * Ce backfill : (1) borne la tranche `min 4 500 001` à 6 000 000 ;
 * (2) insère la tranche `6 000 001 → null @ 27,5 %` (status active,
 * effective_from aligné sur les lignes existantes). Idempotent (gardes
 * sur l'état réel) et search_path-aware (F-17).
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = resolveTableSchema('tax_slabs');

        if ($schema === null) {
            return; // Table absente dans ce contexte — rien à backfiller.
        }

        $table = $schema.'.tax_slabs';

        // effective_from de référence : aligné sur les lignes BF existantes.
        $effectiveFrom = (string) (DB::table($table)
            ->where('country_code', 'BF')
            ->whereNull('company_id')
            ->orderByDesc('effective_from')
            ->value('effective_from') ?? '2024-01-01');

        // (1) La tranche 4 500 001 était ouverte (`max_amount` NULL) depuis
        // #1829 — la borner à 6 000 000 (la suite du barème est la 27,5 %).
        DB::table($table)
            ->where('country_code', 'BF')
            ->whereNull('company_id')
            ->where('min_amount', 4500001)
            ->whereNull('max_amount')
            ->update(['max_amount' => 6000000]);

        // (2) Tranche > 6 000 000 @ 27,5 % (CGI BF 2024) — insert si absente.
        $exists = DB::table($table)
            ->where('country_code', 'BF')
            ->whereNull('company_id')
            ->where('min_amount', 6000001)
            ->exists();

        if (! $exists) {
            DB::table($table)->insert([
                'company_id' => null,
                'country_code' => 'BF',
                'name' => 'BF payroll tax '.substr($effectiveFrom, 0, 4),
                'min_amount' => 6000001,
                'max_amount' => null,
                'rate' => 27.5,
                'fixed_deduction' => 0,
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                // Issue #1813 : config nationale de référence → active.
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Non réversible proprement : la borne 6 000 000 est la valeur légale
        // (le down restaurerait l'état sous-imposé). No-op documenté.
    }
};
