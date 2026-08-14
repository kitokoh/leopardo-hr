<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #2003/#1915 — BF : barème IUTS complet en base pour les bases NON
 * seedées (CI, fresh installs).
 *
 * La migration #2003 (000012) backfillait la 6e tranche (> 6 000 000 @ 27,5 %)
 * en supposant les 5 premières seedées par `PayrollCountryConfigSeeder`.
 * Sur une base migrations-only (CI, fresh install), il ne reste donc qu'UNE
 * ligne BF (27,5 %) → `taxSlabs()` résout la base (partielle) avant le code
 * → sous-imposition massive / IUTS = 0 pour tout brut < 6 M/an → golden BF
 * rouges sur main.
 *
 * Cette migration complète le barème national BF (6 tranches CGI 2024,
 * identiques à `CedeaoPayrollRules::defaultTaxSlabs()` BF, docs/payroll/
 * BF_COMPLIANCE.md §1) en insérant les tranches MANQUANTES par min_amount.
 * Idempotente (F-17) : les environnements seedés (5 + 1 = 6 tranches) sont
 * intouchés ; les bases fraîches reçoivent les 6 tranches.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (! schemaTableExists('tax_slabs')) {
            return;
        }

        $schema = resolveTableSchema('tax_slabs');
        if ($schema === null) {
            return;
        }

        $table = "\"{$schema}\".\"tax_slabs\"";
        $existingMins = DB::table("{$schema}.tax_slabs")
            ->where('country_code', 'BF')
            ->whereNull('company_id')
            ->pluck('min_amount')
            ->map(fn ($v) => (float) $v)
            ->all();

        // Barème légal national BF (CGI 2024) — miroir de defaultTaxSlabs() BF.
        $canonical = [
            [0.0, 600000.0, 0.0],
            [600001.0, 1500000.0, 12.1],
            [1500001.0, 3000000.0, 13.9],
            [3000001.0, 4500000.0, 18.7],
            [4500001.0, 6000000.0, 23.6],
            [6000001.0, null, 27.5],
        ];

        foreach ($canonical as [$min, $max, $rate]) {
            if (in_array($min, $existingMins, true)) {
                continue;
            }

            DB::table("{$schema}.tax_slabs")->insert([
                'country_code' => 'BF',
                'company_id' => null,
                'name' => 'BF payroll tax 2024',
                'min_amount' => $min,
                'max_amount' => $max,
                'rate' => $rate,
                'fixed_deduction' => 0.0,
                'status' => 'active',
                'effective_from' => '2024-01-01',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Pas de rollback : la présence du barème légal complet est l'état sain.
    }
};
