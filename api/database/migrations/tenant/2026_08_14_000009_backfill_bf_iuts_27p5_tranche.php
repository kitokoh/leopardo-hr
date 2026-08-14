<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #2003 — backfill BF : les tenants seedés depuis #1829 gardent 5
 * tranches IUTS en base alors que #1972 a rétabli la 6ᵉ tranche
 * (> 6 000 000 FCFA/an @ 27,5 %) dans `CedeaoPayrollRules::defaultTaxSlabs()`.
 *
 * `AbstractCountryRules::taxSlabs()` résout la base AVANT le code et
 * `PayrollCountryConfigSeeder` re-seedait depuis `taxSlabs()` → no-op
 * silencieux. Cette migration corrige les lignes EXISTANTES des tenants BF :
 *   1. la tranche `4 500 001` passe de `max = null` à `max = 6 000 000` ;
 *   2. insertion de la tranche `6 000 001 → null @ 27,5 %` (même
 *      effective_from/status que les lignes existantes).
 *
 * Idempotente (garde d'existence par tranche) et qualifiée par schéma
 * (pattern F-17, #1933) : exécutée dans le schéma tenant courant.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tax_slabs')) {
            return;
        }

        // Tranche IUTS BF (annuel, FCFA) — cf. CedeaoPayrollRules::defaultTaxSlabs().
        $slab4500001 = ['min' => 4500001, 'rate' => 23.6];
        $slab6000001 = ['min' => 6000001, 'rate' => 27.5];

        $bfRows = DB::table('tax_slabs')
            ->where('country_code', 'BF')
            ->whereNull('company_id')
            ->where('status', 'active')
            ->orderBy('min_amount')
            ->get();

        foreach ($bfRows as $row) {
            $min = (float) $row->min_amount;
            $rate = (float) $row->rate;

            // 1. Tranche 4 500 001 @ 23,6 % : plafonner à 6 000 000.
            if (abs($min - $slab4500001['min']) < 0.01 && abs($rate - $slab4500001['rate']) < 0.01) {
                if ($row->max_amount === null) {
                    DB::table('tax_slabs')
                        ->where('id', $row->id)
                        ->update(['max_amount' => 6000000]);
                }
                continue;
            }

            // 2. Insérer la 6ᵉ tranche si absente (toujours après la 4,5 M).
            if (abs($min - $slab6000001['min']) < 0.01) {
                continue; // déjà présente
            }
        }

        $alreadyHasSixth = $bfRows->contains(
            fn ($row): bool => abs((float) $row->min_amount - 6000001) < 0.01
        );

        if (! $alreadyHasSixth) {
            // effective_from aligné sur les lignes existantes (2024-01-01 pour
            // BF via PayrollCountryConfigSeeder, sinon 2026-01-01 historique).
            $reference = $bfRows->first();
            $effectiveFrom = $reference->effective_from ?? '2024-01-01';

            DB::table('tax_slabs')->insert([
                'company_id' => null,
                'country_code' => 'BF',
                'name' => 'BF payroll tax 2024',
                'min_amount' => 6000001,
                'max_amount' => null,
                'rate' => 27.5,
                'fixed_deduction' => 0,
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Backfill correctif : la descente restaurerait l'état sous-imposé —
        // on ne fait rien (les données restent cohérentes avec le code #1972).
    }
};
